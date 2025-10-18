<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use ZipArchive;

class KycVerificationController extends Controller
{
    use UploadedFile;
    public function index(Request $request): \Inertia\Response
    {
        $query = KycVerification::with(['user', 'reviewer']);
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('document_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->get('document_type'));
        }

        $sortField = $request->get('sort_field', 'submitted_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSortFields = ['submitted_at', 'status', 'document_type', 'created_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $perPage = $request->get('per_page', 20);
        $verifications = $query->paginate($perPage)->appends($request->all());
        $transformedVerifications = $verifications->getCollection()->map(function ($verification) {
            return [
                'id' => $verification->id,
                'user_id' => $verification->user_id,
                'user' => $verification->user,
                'reviewer' => $verification->reviewer,
                'first_name' => $verification->first_name,
                'last_name' => $verification->last_name,
                'full_name' => $verification->full_name,
                'date_of_birth' => $verification->date_of_birth,
                'phone' => $verification->phone,
                'address' => $verification->address,
                'city' => $verification->city,
                'state' => $verification->state,
                'country' => $verification->country,
                'postal_code' => $verification->postal_code,
                'document_type' => $verification->document_type,
                'document_number' => $verification->document_number,
                'document_front_url' => $verification->document_front_url,
                'document_back_url' => $verification->document_back_url,
                'selfie_url' => $verification->selfie_url,
                'status' => $verification->status,
                'rejection_reason' => $verification->rejection_reason,
                'submitted_at' => $verification->submitted_at,
                'reviewed_at' => $verification->reviewed_at,
                'reviewed_by' => $verification->reviewed_by,
                'created_at' => $verification->created_at,
                'updated_at' => $verification->updated_at,
            ];
        });

        return Inertia::render('Admin/KycVerifications/Index', [
            'verifications' => $transformedVerifications,
            'meta' => [
                'total' => $verifications->total(),
                'current_page' => $verifications->currentPage(),
                'per_page' => $verifications->perPage(),
                'last_page' => $verifications->lastPage(),
            ],
            'filters' => [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'document_type' => $request->get('document_type'),
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection,
            ],
            'currentUser' => auth()->user(),
        ]);
    }

    public function updateStatus(Request $request, KycVerification $kycVerification): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['pending', 'reviewing', 'approved', 'rejected'])],
            'rejection_reason' => 'nullable|string|max:1000'
        ]);

        $newStatus = $request->status;
        $updateData = [
            'status' => $newStatus,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ];

        if ($newStatus === 'rejected' && $request->filled('rejection_reason')) {
            $updateData['rejection_reason'] = $request->rejection_reason;
        }

        $kycVerification->update($updateData);
        $user = $kycVerification->user;
        if ($user) {
            $user->update(['kyc_status' => $newStatus]);
        }

        return redirect()->back()->with('success', 'KYC verification status updated successfully.');
    }

    public function destroy(KycVerification $kycVerification): \Illuminate\Http\RedirectResponse
    {
        if ($kycVerification->document_front_path) {
            $this->removeFile($kycVerification->document_front_path);
        }
        if ($kycVerification->document_back_path) {
            $this->removeFile($kycVerification->document_back_path);
        }
        if ($kycVerification->selfie_path) {
            $this->removeFile($kycVerification->selfie_path);
        }

        $kycVerification->delete();

        return redirect()->back()->with('success', 'KYC verification deleted successfully.');
    }


    public function downloadAllDocuments(KycVerification $kycVerification)
    {
        $hasDocuments = false;
        $documents = [];
        if ($kycVerification->document_front_path && $this->fileExists($kycVerification->document_front_path)) {
            $documents['document_front'] = [
                'path' => $kycVerification->document_front_path,
                'name' => 'document_front.' . pathinfo($kycVerification->document_front_path, PATHINFO_EXTENSION)
            ];
            $hasDocuments = true;
        }

        if ($kycVerification->document_back_path && $this->fileExists($kycVerification->document_back_path)) {
            $documents['document_back'] = [
                'path' => $kycVerification->document_back_path,
                'name' => 'document_back.' . pathinfo($kycVerification->document_back_path, PATHINFO_EXTENSION)
            ];
            $hasDocuments = true;
        }

        if ($kycVerification->selfie_path && $this->fileExists($kycVerification->selfie_path)) {
            $documents['selfie'] = [
                'path' => $kycVerification->selfie_path,
                'name' => 'selfie.' . pathinfo($kycVerification->selfie_path, PATHINFO_EXTENSION)
            ];
            $hasDocuments = true;
        }

        if (!$hasDocuments) {
            abort(404, 'No documents found');
        }

        if (count($documents) === 1) {
            $document = array_values($documents)[0];
            return Storage::disk('public')->download($document['path'], $document['name']);
        }

        $zipFileName = 'kyc_documents_' . $kycVerification->id . '_' . time() . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        if (!file_exists(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
            foreach ($documents as $key => $document) {
                if ($this->fileExists($document['path'])) {
                    $zip->addFile('assets/files/' . $document['path'], $document['name']);
                }
            }
            $zip->close();
            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        } else {
            abort(500, 'Could not create ZIP file');
        }
    }
}
