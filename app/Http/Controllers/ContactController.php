<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactFormSubmission;
use App\Models\ContactSubmission;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|in:account,technical,billing,gcu,api,compliance,other',
            'message' => 'required|string|max:5000',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,png,jpg,jpeg,doc,docx',
        ]);

        // Handle file upload if present
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('contact-attachments', 'local');
        }

        // Save to database
        $submission = ContactSubmission::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
            'attachment_path' => $attachmentPath,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Send email notification to admin
        Mail::to('info@finaegis.org')->send(new ContactFormSubmission($submission));

        return redirect()->back()->with('success', 'Thank you for contacting us. We will respond to your inquiry as soon as possible.');
    }
}