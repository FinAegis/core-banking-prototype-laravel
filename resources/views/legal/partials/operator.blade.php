{{-- The operator's registry details, shown under the legal pages' contact headings. --}}
Address: {{ config('brand.legal_address') ?: config('brand.legal_jurisdiction') }}
@if(config('brand.legal_company_code'))
<br>Company code: {{ config('brand.legal_company_code') }}
@endif
@if(config('brand.legal_vat_code'))
<br>VAT code: {{ config('brand.legal_vat_code') }}
@endif
