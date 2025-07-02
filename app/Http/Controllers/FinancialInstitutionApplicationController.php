<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class FinancialInstitutionApplicationController extends Controller
{
    /**
     * Display the application form
     */
    public function show()
    {
        return view('financial-institutions.apply');
    }

    /**
     * Handle form submission
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'institution_name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'technical_capabilities' => 'required|string|min:50',
            'regulatory_compliance' => 'required|string|min:50',
            'financial_strength' => 'required|string|min:50',
            'insurance_coverage' => 'required|string|min:50',
            'partnership_vision' => 'nullable|string',
            'terms' => 'required|accepted',
        ], [
            'terms.required' => 'Please accept the terms and conditions.',
            'terms.accepted' => 'Please accept the terms and conditions.',
            'technical_capabilities.min' => 'Please provide at least 50 characters describing your technical capabilities.',
            'regulatory_compliance.min' => 'Please provide at least 50 characters describing your regulatory compliance.',
            'financial_strength.min' => 'Please provide at least 50 characters describing your financial strength.',
            'insurance_coverage.min' => 'Please provide at least 50 characters describing your insurance coverage.',
        ]);

        // Log the application
        Log::info('New financial institution application received', [
            'institution' => $validated['institution_name'],
            'country' => $validated['country'],
            'contact' => $validated['contact_name'],
            'email' => $validated['contact_email'],
        ]);

        // In production, you would:
        // 1. Store in database
        // 2. Send email notifications
        // 3. Create a case in CRM
        // 4. Trigger due diligence workflow

        // For now, we'll just log and redirect with success
        return redirect()
            ->route('financial-institutions.apply')
            ->with('success', 'Thank you for your application. We will review it and contact you soon.');
    }
}