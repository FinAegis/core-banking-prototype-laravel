<x-mail::message>
# New Contact Form Submission

**Priority:** {{ ucfirst($submission->priority) }}  
**Subject:** {{ $submission->subject_label }}

## Contact Details

**Name:** {{ $submission->name }}  
**Email:** {{ $submission->email }}  
**Submitted:** {{ $submission->created_at->format('F j, Y at g:i A') }}

## Message

{{ $submission->message }}

## Additional Information

- **IP Address:** {{ $submission->ip_address }}
- **Submission ID:** #{{ $submission->id }}

<x-mail::button :url="url('/admin/contact-submissions/' . $submission->id)">
View in Admin Panel
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>