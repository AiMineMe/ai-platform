<?php

namespace App\Http\Controllers\User;

use App\Enums\User\Status;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\Setting;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Exception;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SettingsController extends Controller
{
    use \App\Concerns\UploadedFile;
    private const MAX_FILE_SIZE = 10240;

    private const ALLOWED_IMAGE_TYPES = ['jpeg', 'png', 'jpg'];

    private const ALLOWED_DOCUMENT_TYPES = ['passport', 'driver_license', 'national_id'];

    /**
     * Display the user profile settings page
     * @return Response|RedirectResponse
     */
    public function profileIndex(): Response | RedirectResponse
    {
        try {
            $user = Auth::user();
            return Inertia::render('User/Settings/Profile', [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'last_login_at' => $user->last_login_at,
                    'role' => $user->role,
                    'kyc_status' => $user->kyc_status,
                    'avatar' => $user->avatar,
                    'avatar_url' => $user->avatar ? asset('assets/files/'.$user->avatar) : null,
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Profile index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to load profile page. Please try again.');
        }
    }

    /**
     * Update user profile information
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws ValidationException
     */
    /**
     * Update user profile information
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws ValidationException
     */
    public function updateProfile(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $user = Auth::user();
            $validatedData = $request->validate([
                'name' => ['required','string','max:255','min:2','regex:/^[a-zA-Z\s]+$/'],
                'email' => ['required','string','email','max:255',Rule::unique('users')->ignore($user->id)],
                'phone' => ['required','string','max:20','min:10','regex:/^[\+]?[0-9\s\-\(\)]+$/',Rule::unique('users')->ignore($user->id)],
                'avatar' => ['nullable','image','mimes:' . implode(',', self::ALLOWED_IMAGE_TYPES),'max:2048'],
            ], [
                'name.regex' => 'Name can only contain letters and spaces.',
                'email.email' => 'Please provide a valid email address.',
                'phone.regex' => 'Phone number can only contain numbers, spaces, hyphens, parentheses, and plus sign.',
                'phone.unique' => 'This phone number is already registered.',
                'phone.min' => 'Phone number must be at least 10 characters long.',
                'phone.max' => 'Phone number must not exceed 20 characters.',
                'avatar.dimensions' => 'Avatar must be between 100x100 and 2000x2000 pixels.',
                'avatar.max' => 'Avatar size must not exceed 2MB.',
            ]);

            DB::beginTransaction();
            $updateData = [
                'name' => trim($validatedData['name']),
                'email' => strtolower(trim($validatedData['email'])),
                'phone' => preg_replace('/[^0-9+]/', '', trim($validatedData['phone']))
            ];

            $emailChanged = $user->email !== $updateData['email'];
            if ($emailChanged) {
                $updateData['email_verified_at'] = null;
            }

            if ($request->hasFile('avatar')) {
                $avatarFile = $request->file('avatar');
                if (!$avatarFile->isValid()) {
                    throw ValidationException::withMessages([
                        'avatar' => ['The uploaded avatar file is corrupted. Please try again.']
                    ]);
                }

                $avatarFileName = $user->avatar;
                $avatarPath = $this->move($avatarFile, null, $avatarFileName);
                $updateData['avatar'] = $avatarPath;
            }

            $user->update($updateData);
            if ($emailChanged && EmailVerificationService::isVerificationRequired()) {
                EmailVerificationService::sendVerificationEmail($user);
                DB::commit();

                Auth::logout();
            } else {
                $user->update([
                    'status' => Status::ACTIVE->value,
                    'email_verified_at' => now()
                ]);
            }

            DB::commit();

            $message = 'Profile updated successfully.';
            if ($emailChanged) {
                $message = 'Profile updated successfully. Please check your new email to verify it.';
            }
            return redirect()->back()->with('success', $message);
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to update profile. Please try again.');
        }
    }

    /**
     * Remove user avatar
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeAvatar(): \Illuminate\Http\RedirectResponse
    {
        try {
            $user = Auth::user();
            if ($user->avatar) {
               $this->removeFile($user->avatar);
                $user->update(['avatar' => null]);
            }

            return back()->with('success', 'Avatar removed successfully.');

        } catch (Exception $e) {
            Log::error('Avatar removal error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to remove avatar. Please try again.');
        }
    }

    /**
     * Display the KYC verification page
     * @return Response|\Illuminate\Http\RedirectResponse
     */
    public function kycIndex(): Response | \Illuminate\Http\RedirectResponse
    {
        try {
            $isKYCEnabled = Setting::get('kyc_status', true);
            if (!$isKYCEnabled) {
                return redirect()->route('user.dashboard')->withErrors([
                    'error' => 'KYC verification is currently disabled.'
                ]);
            }

            $user = Auth::user();
            $kyc = $user->kycVerification;

            return Inertia::render('User/Settings/KYC', [
                'kyc' => $kyc ? [
                    'id' => $kyc->id,
                    'first_name' => $kyc->first_name,
                    'last_name' => $kyc->last_name,
                    'date_of_birth' => $kyc->date_of_birth->format('Y-m-d'),
                    'phone' => $kyc->phone,
                    'address' => $kyc->address,
                    'city' => $kyc->city,
                    'state' => $kyc->state,
                    'country' => $kyc->country,
                    'postal_code' => $kyc->postal_code,
                    'document_type' => $kyc->document_type,
                    'document_number' => $kyc->document_number,
                    'status' => $kyc->status,
                    'status_label' => $kyc->status_label,
                    'status_color' => $kyc->status_color,
                    'rejection_reason' => $kyc->rejection_reason,
                    'submitted_at' => $kyc->submitted_at,
                    'reviewed_at' => $kyc->reviewed_at,
                    'document_front_url' => $kyc->document_front_url,
                    'document_back_url' => $kyc->document_back_url,
                    'selfie_url' => $kyc->selfie_url,
                ] : null,
                'countries' => $this->getCountries(),
            ]);
        } catch (Exception $e) {
            Log::error('KYC index error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Unable to load KYC page. Please try again.');
        }
    }

    /**
     * Submit new KYC verification
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws ValidationException
     */
    public function submitKyc(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $isKYCEnabled = Setting::get('kyc_status', true);
            if (!$isKYCEnabled) {
                return redirect()->route('user.dashboard')->withErrors([
                    'error' => 'KYC verification is currently disabled.'
                ]);
            }

            $user = Auth::user();
            if ($user->kycVerification && !in_array($user->kycVerification->status, ['rejected'])) {
                return redirect()->back()->with('error', 'KYC verification already submitted.');
            }

            $validatedData = $this->validateKycData($request, true);
            DB::beginTransaction();

            $filePaths = $this->handleKycFileUploads($request, $user->id);
            KycVerification::create([
                'user_id' => $user->id,
                'first_name' => trim($validatedData['first_name']),
                'last_name' => trim($validatedData['last_name']),
                'date_of_birth' => $validatedData['date_of_birth'],
                'phone' => $this->sanitizePhoneNumber($validatedData['phone']),
                'address' => trim($validatedData['address']),
                'city' => trim($validatedData['city']),
                'state' => trim($validatedData['state']),
                'country' => $validatedData['country'],
                'postal_code' => trim($validatedData['postal_code']),
                'document_type' => $validatedData['document_type'],
                'document_number' => strtoupper(trim($validatedData['document_number'])),
                'document_front_path' => $filePaths['document_front'],
                'document_back_path' => $filePaths['document_back'],
                'selfie_path' => $filePaths['selfie'],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            $user->update(['kyc_status' => 'pending']);
            DB::commit();

            Log::info('KYC verification submitted', [
                'user_id' => $user->id,
                'document_type' => $validatedData['document_type']
            ]);

            return redirect()->back()->with('success', 'KYC verification submitted successfully. We will review your documents within 2-3 business days.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('KYC submission error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to submit KYC verification. Please try again.');
        }
    }

    /**
     * Resubmit KYC verification after rejection
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws ValidationException
     */
    public function resubmitKyc(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            $isKYCEnabled = Setting::get('kyc_status', true);
            if (!$isKYCEnabled) {
                return redirect()->route('user.dashboard')->withErrors([
                    'error' => 'KYC verification is currently disabled.'
                ]);
            }

            $user = Auth::user();
            $kyc = $user->kycVerification;

            if (!$kyc || $kyc->status !== 'rejected') {
                return redirect()->back()->with('error', 'Invalid KYC resubmission request.');
            }

            $validatedData = $this->validateKycData($request, false);
            DB::beginTransaction();

            $updateData = [
                'first_name' => trim($validatedData['first_name']),
                'last_name' => trim($validatedData['last_name']),
                'date_of_birth' => $validatedData['date_of_birth'],
                'phone' => $this->sanitizePhoneNumber($validatedData['phone']),
                'address' => trim($validatedData['address']),
                'city' => trim($validatedData['city']),
                'state' => trim($validatedData['state']),
                'country' => $validatedData['country'],
                'postal_code' => trim($validatedData['postal_code']),
                'document_type' => $validatedData['document_type'],
                'document_number' => strtoupper(trim($validatedData['document_number'])),
                'status' => 'pending',
                'rejection_reason' => null,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
            ];

            $this->handleKycFileResubmission($request, $kyc, $updateData);
            $kyc->update($updateData);
            $user->update(['kyc_status' => 'pending']);

            DB::commit();

            Log::info('KYC verification resubmitted', [
                'user_id' => $user->id,
                'kyc_id' => $kyc->id
            ]);

            return redirect()->back()->with('success', 'KYC verification resubmitted successfully. We will review your updated documents within 2-3 business days.');

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('KYC resubmission error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'kyc_id' => $kyc->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to resubmit KYC verification. Please try again.');
        }
    }

    /**
     * Validate KYC form data
     * @param Request $request
     * @param bool $filesRequired
     * @return array
     */
    private function validateKycData(Request $request, bool $filesRequired = true): array
    {
        $rules = [
            'first_name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
            'last_name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z\s\-\'\.]+$/'
            ],
            'date_of_birth' => [
                'required',
                'date',
                'before:today',
                'after:1900-01-01'
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/'
            ],
            'address' => 'required|string|max:500|min:10',
            'city' => 'required|string|max:255|min:2',
            'state' => 'required|string|max:255|min:2',
            'country' => 'required|string|max:255|in:' . implode(',', $this->getCountries()),
            'postal_code' => 'required|string|max:20|min:3',
            'document_type' => 'required|in:' . implode(',', self::ALLOWED_DOCUMENT_TYPES),
            'document_number' => [
                'required',
                'string',
                'max:50',
                'min:3',
                'regex:/^[A-Z0-9\-\s]+$/i'
            ],
        ];

        $fileRules = $filesRequired ? 'required|' : 'nullable|';
        $fileRules .= 'image|mimes:' . implode(',', self::ALLOWED_IMAGE_TYPES) . '|max:' . self::MAX_FILE_SIZE;

        $rules['document_front'] = $fileRules;
        $rules['document_back'] = str_replace('required|', 'nullable|', $fileRules);
        $rules['selfie'] = $fileRules;

        $messages = [
            'first_name.regex' => 'First name contains invalid characters.',
            'last_name.regex' => 'Last name contains invalid characters.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'date_of_birth.after' => 'Date of birth must be after 1900.',
            'phone.regex' => 'Please provide a valid phone number.',
            'address.min' => 'Address must be at least 10 characters long.',
            'document_number.regex' => 'Document number contains invalid characters.',
            'document_front.max' => 'Document front image must not exceed 10MB.',
            'document_back.max' => 'Document back image must not exceed 10MB.',
            'selfie.max' => 'Selfie image must not exceed 10MB.',
        ];

        return $request->validate($rules, $messages);
    }

    /**
     * Handle KYC file uploads for new submission
     * @param Request $request
     * @param int $userId
     * @return array File paths
     * @throws Exception
     */
    private function handleKycFileUploads(Request $request, int $userId): array
    {
        $filePaths = [];
        try {
            if ($request->hasFile('document_front')) {
                $file = $request->file('document_front');
                $this->validateFileIntegrity($file, 'document_front');
                $filePaths['document_front'] = $this->move($file);
            }

            if ($request->hasFile('selfie')) {
                $file = $request->file('selfie');
                $this->validateFileIntegrity($file, 'selfie');
                $filePaths['selfie'] = $this->move($file);
            }

            $filePaths['document_back'] = null;
            if ($request->hasFile('document_back')) {
                $file = $request->file('document_back');
                $this->validateFileIntegrity($file, 'document_back');
                $filePaths['document_back'] = $this->move($file);
            }

            return $filePaths;

        } catch (Exception $e) {
            foreach ($filePaths as $path) {
                $this->removeFile($path);
            }
            throw $e;
        }
    }

    /**
     * Handle KYC file uploads for resubmission
     * @param Request $request
     * @param KycVerification $kyc
     * @param array &$updateData
     * @return void
     * @throws Exception
     */
    private function handleKycFileResubmission(Request $request, KycVerification $kyc, array &$updateData): void
    {
        if ($request->hasFile('document_front')) {
            $file = $request->file('document_front');
            $this->validateFileIntegrity($file, 'document_front');
            $updateData['document_front_path'] = $this->move($file, null, $kyc->document_front_path);
        }

        if ($request->hasFile('document_back')) {
            $file = $request->file('document_back');
            $this->validateFileIntegrity($file, 'document_back');
            $updateData['document_back_path'] = $this->move($file, null, $kyc->document_back_path);
        }

        if ($request->hasFile('selfie')) {
            $file = $request->file('selfie');
            $this->validateFileIntegrity($file, 'selfie');
            $updateData['selfie_path'] = $this->move($file, null, $kyc->selfie_path);
        }
    }

    /**
     * Validate file integrity
     *
     * @param UploadedFile $file
     * @param string $fieldName
     * @return void
     * @throws ValidationException
     */
    private function validateFileIntegrity(UploadedFile $file, string $fieldName): void
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                $fieldName => ["The uploaded {$fieldName} file is corrupted or invalid. Please try again."]
            ]);
        }

        $detectedType = $file->getMimeType();
        $allowedMimeTypes = ['image/jpeg', 'image/png'];

        if (!in_array($detectedType, $allowedMimeTypes)) {
            throw ValidationException::withMessages([
                $fieldName => ["Invalid file type detected for {$fieldName}. Only JPEG and PNG files are allowed."]
            ]);
        }

        $extension = $file->getClientOriginalExtension();
        $allowedExtensions = ['jpg', 'jpeg', 'png'];

        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw ValidationException::withMessages([
                $fieldName => ["Invalid file extension for {$fieldName}. Only JPG, JPEG, and PNG files are allowed."]
            ]);
        }
    }

    /**
     * Sanitize phone number
     * @param string $phone
     * @return string
     */
    private function sanitizePhoneNumber(string $phone): string
    {
        $sanitized = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($sanitized, '+')) {
            $sanitized = '+' . preg_replace('/[^0-9]/', '', substr($sanitized, 1));
        } else {
            $sanitized = preg_replace('/[^0-9]/', '', $sanitized);
        }

        return $sanitized;
    }

    /**
     * Get list of supported countries
     * @return array List of country names
     */
    private function getCountries(): array
    {
        return  [
            'Afghanistan',
            'Åland Islands',
            'Albania',
            'Algeria',
            'American Samoa',
            'AndorrA',
            'Angola',
            'Anguilla',
            'Antarctica',
            'Antigua and Barbuda',
            'Argentina',
            'Armenia',
            'Aruba',
            'Australia',
            'Austria',
            'Azerbaijan',
            'Bahamas',
            'Bahrain',
            'Bangladesh',
            'Barbados',
            'Belarus',
            'Belgium',
            'Belize',
            'Benin',
            'Bermuda',
            'Bhutan',
            'Bolivia',
            'Bosnia and Herzegovina',
            'Botswana',
            'Bouvet Island',
            'Brazil',
            'British Indian Ocean Territory',
            'Brunei Darussalam',
            'Bulgaria',
            'Burkina Faso',
            'Burundi',
            'Cambodia',
            'Cameroon',
            'Canada',
            'Cape Verde',
            'Cayman Islands',
            'Central African Republic',
            'Chad',
            'Chile',
            'China',
            'Christmas Island',
            'Cocos (Keeling) Islands',
            'Colombia',
            'Comoros',
            'Congo',
            'Congo, The Democratic Republic of the',
            'Cook Islands',
            'Costa Rica',
            'Cote D\'Ivoire',
            'Croatia',
            'Cuba',
            'Cyprus',
            'Czech Republic',
            'Denmark',
            'Djibouti',
            'Dominica',
            'Dominican Republic',
            'Ecuador',
            'Egypt',
            'El Salvador',
            'Equatorial Guinea',
            'Eritrea',
            'Estonia',
            'Ethiopia',
            'Falkland Islands (Malvinas)',
            'Faroe Islands',
            'Fiji',
            'Finland',
            'France',
            'French Guiana',
            'French Polynesia',
            'French Southern Territories',
            'Gabon',
            'Gambia',
            'Georgia',
            'Germany',
            'Ghana',
            'Gibraltar',
            'Greece',
            'Greenland',
            'Grenada',
            'Guadeloupe',
            'Guam',
            'Guatemala',
            'Guernsey',
            'Guinea',
            'Guinea-Bissau',
            'Guyana',
            'Haiti',
            'Heard Island and Mcdonald Islands',
            'Holy See (Vatican City State)',
            'Honduras',
            'Hong Kong',
            'Hungary',
            'Iceland',
            'India',
            'Indonesia',
            'Iran, Islamic Republic Of',
            'Iraq',
            'Ireland',
            'Isle of Man',
            'Israel',
            'Italy',
            'Jamaica',
            'Japan',
            'Jersey',
            'Jordan',
            'Kazakhstan',
            'Kenya',
            'Kiribati',
            'Korea, Democratic People\'S Republic of',
            'Korea, Republic of',
            'Kuwait',
            'Kyrgyzstan',
            'Lao People\'S Democratic Republic',
            'Latvia',
            'Lebanon',
            'Lesotho',
            'Liberia',
            'Libyan Arab Jamahiriya',
            'Liechtenstein',
            'Lithuania',
            'Luxembourg',
            'Macao',
            'Macedonia, The Former Yugoslav Republic of',
            'Madagascar',
            'Malawi',
            'Malaysia',
            'Maldives',
            'Mali',
            'Malta',
            'Marshall Islands',
            'Martinique',
            'Mauritania',
            'Mauritius',
            'Mayotte',
            'Mexico',
            'Micronesia, Federated States of',
            'Moldova, Republic of',
            'Monaco',
            'Mongolia',
            'Montserrat',
            'Morocco',
            'Mozambique',
            'Myanmar',
            'Namibia',
            'Nauru',
            'Nepal',
            'Netherlands',
            'Netherlands Antilles',
            'New Caledonia',
            'New Zealand',
            'Nicaragua',
            'Niger',
            'Nigeria',
            'Niue',
            'Norfolk Island',
            'Northern Mariana Islands',
            'Norway',
            'Oman',
            'Pakistan',
            'Palau',
            'Palestinian Territory, Occupied',
            'Panama',
            'Papua New Guinea',
            'Paraguay',
            'Peru',
            'Philippines',
            'Pitcairn',
            'Poland',
            'Portugal',
            'Puerto Rico',
            'Qatar',
            'Reunion',
            'Romania',
            'Russian Federation',
            'RWANDA',
            'Saint Helena',
            'Saint Kitts and Nevis',
            'Saint Lucia',
            'Saint Pierre and Miquelon',
            'Saint Vincent and the Grenadines',
            'Samoa',
            'San Marino',
            'Sao Tome and Principe',
            'Saudi Arabia',
            'Senegal',
            'Serbia and Montenegro',
            'Seychelles',
            'Sierra Leone',
            'Singapore',
            'Slovakia',
            'Slovenia',
            'Solomon Islands',
            'Somalia',
            'South Africa',
            'South Georgia and the South Sandwich Islands',
            'Spain',
            'Sri Lanka',
            'Sudan',
            'Suriname',
            'Svalbard and Jan Mayen',
            'Swaziland',
            'Sweden',
            'Switzerland',
            'Syrian Arab Republic',
            'Taiwan, Province of China',
            'Tajikistan',
            'Tanzania, United Republic of',
            'Thailand',
            'Timor-Leste',
            'Togo',
            'Tokelau',
            'Tonga',
            'Trinidad and Tobago',
            'Tunisia',
            'Turkey',
            'Turkmenistan',
            'Turks and Caicos Islands',
            'Tuvalu',
            'Uganda',
            'Ukraine',
            'United Arab Emirates',
            'United Kingdom',
            'United States',
            'United States Minor Outlying Islands',
            'Uruguay',
            'Uzbekistan',
            'Vanuatu',
            'Venezuela',
            'Viet Nam',
            'Virgin Islands, British',
            'Virgin Islands, U.S.',
            'Wallis and Futuna',
            'Western Sahara',
            'Yemen',
            'Zambia',
            'Zimbabwe'
        ];
    }
}
