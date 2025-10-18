<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\UploadedFile;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    use UploadedFile;
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = SupportTicket::with(['user', 'assignedTo', 'replies'])->withCount('replies');
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('priority') && !empty($request->priority)) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to)) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        $tickets = $query->paginate(20)->withQueryString();
        $adminUsers = User::where('role', 'admin')->get(['id', 'name']);
        $stats = [
            'totalTickets' => SupportTicket::count(),
            'openTickets' => SupportTicket::where('status', 'open')->count(),
            'inProgressTickets' => SupportTicket::where('status', 'in_progress')->count(),
            'urgentTickets' => SupportTicket::where('priority', 'urgent')->count(),
        ];

        return Inertia::render('Admin/SupportTickets/Index', [
            'tickets' => $tickets->items(),
            'meta' => [
                'total' => $tickets->total(),
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'last_page' => $tickets->lastPage(),
            ],
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'priority', 'assigned_to', 'sort_field', 'sort_direction']),
            'adminUsers' => $adminUsers,
        ]);
    }

    /**
     * @param SupportTicket $ticket
     * @return Response
     */
    public function show(SupportTicket $ticket): Response
    {
        $ticket->load([
            'user',
            'assignedTo',
            'replies.user',
            'replies.attachments',
            'attachments'
        ]);

        $adminUsers = User::where('role', 'admin')->get(['id', 'name']);
        return Inertia::render('Admin/SupportTickets/Show', [
            'ticket' => $ticket,
            'adminUsers' => $adminUsers,
        ]);
    }

    /**
     * @param Request $request
     * @param SupportTicket $ticket
     * @return RedirectResponse
     */
    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $ticket->update([
            'status' => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'Ticket status updated successfully');
    }

    public function updatePriority(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $ticket->update(['priority' => $request->priority]);
        return back()->with('success', 'Ticket priority updated successfully');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $ticket->update(['assigned_to' => $request->assigned_to]);
        return back()->with('success', 'Ticket assigned successfully');
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:5000',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf,doc,docx,txt,zip',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_admin_reply' => true,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getClientMimeType();
                $fileSize = $file->getSize();

                $filename = $this->move($file);
                SupportTicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'ticket_reply_id' => $reply->id,
                    'filename' => $filename,
                    'original_name' => $originalName,
                    'file_path' => $filename,
                    'file_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);
            }
        }

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Reply added successfully');
    }

    /**
     * @param SupportTicket $ticket
     * @return RedirectResponse
     */
    public function destroy(SupportTicket $ticket)
    {
        try {
            $attachments = SupportTicketAttachment::where('ticket_id', $ticket->id)->get();
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $ticket->delete();

            return back()->with('success', 'Support ticket deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete support ticket');
        }
    }

    public function downloadAttachment(SupportTicketAttachment $attachment): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return $this->download($attachment->file_path);
    }

    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_ids' => 'required|array',
            'ticket_ids.*' => 'exists:support_tickets,id',
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        SupportTicket::whereIn('id', $request->ticket_ids)->update([
            'status' => $request->status,
            'resolved_at' => $request->status === 'resolved' ? now() : null,
        ]);

        return back()->with('success', 'Selected tickets status updated successfully');
    }
}
