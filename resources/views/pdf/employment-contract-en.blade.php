<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <title>Employment Contract Draft (Translation)</title>
    @include('pdf.partials.employment-contract-styles')
</head>
<body>
    <div class="draft-banner">DRAFT — مسودة — Not signed or legally approved</div>

    <p class="ltr-note"><strong>Language:</strong> This English text is a translation for convenience. The Arabic contract is the authoritative version; if the texts differ, the Arabic text controls.</p>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if(!empty($logo_path))
                        <img src="{{ $logo_path }}" alt="Logo">
                    @endif
                </td>
                <td class="company-block">
                    <div class="name">{{ $employer_legal_name }}</div>
                    <div>{{ $employer_address }}</div>
                    @if(filled($employer_registration))
                        <div>Registration: {{ $employer_registration }}</div>
                    @endif
                    <div>Authorized signatory: {{ $signatory_name }} — {{ $signatory_title }}</div>
                </td>
            </tr>
        </table>
    </div>

    <h1>Individual Remote Employment Contract — Draft</h1>

    <p>On {{ $draft_date_formatted }}, the following parties agree:</p>

    <p><strong>First:</strong> {{ $employer_legal_name }}, address {{ $employer_address }}@if(filled($employer_registration)), registration {{ $employer_registration }}@endif, represented for signature by {{ $signatory_name }} in the capacity of {{ $signatory_title }} (“Employer”).</p>

    <p><strong>Second:</strong> {{ $employee_name }}@if($employee_national_id), holding national ID number {{ $employee_national_id }}@endif (“Employee”).</p>

    <h2>Preamble</h2>
    <p class="clause">The parties agree that the Employee shall perform work for the Employer under its management or supervision for wages, on the terms below and in accordance with Egyptian Labour Law No. 14 of 2025, Social Insurance and Pensions Law No. 148 of 2019 and amendments, and applicable regulations. This preamble forms an integral part of the contract.</p>

    <h2>1. Position</h2>
    <p class="clause">The Employer appoints the Employee to the position of {{ $employee_job_title }}. The Employee may not be assigned work that differs materially from the agreed work except as permitted by law.</p>

    <h2>2. Place and mode of work</h2>
    <p class="clause">Work is performed remotely using agreed communication and technology. Both parties remain subject to statutory employment rights and duties, including social protection and insurance, having regard to remote work. Remote work does not reduce statutory protections.</p>

    <h2>3. Term</h2>
    @if($is_fixed_term)
        <p class="clause">This is a fixed-term contract from {{ $start_date_formatted }} until {{ $end_date_formatted }}, for {{ $fixed_term_months }} months, because the nature of the work requires a fixed term for the following reason: {{ $fixed_term_reason }}. The contract ends when the term expires unless the parties agree in writing to renew it before expiry. The contract must not continue after expiry without a written renewal. Any termination before expiry is governed by applicable law.</p>
    @else
        <p class="clause">This contract starts on {{ $start_date_formatted }} and is of indefinite duration.</p>
    @endif

    <h2>4. Probation</h2>
    <p class="clause">The Employee is subject to a probation period of two months starting on the work start date. Probation may not exceed the maximum permitted by law and may not be applied again with the same Employer.</p>

    <h2>5. Working hours and rest</h2>
    <p class="clause">Statutory limits on working hours, breaks, weekly rest, and overtime under Egyptian labour law apply, including limits on daily and weekly working time and mandatory rest periods.</p>

    <h2>6. Wages</h2>
    <p class="clause">The Employee receives total monthly wages of {{ $full_salary }} {{ $salary_currency }}. The Employer shall set out wage components and lawful deductions on the wage statement. Lawful contributions and deductions apply without prejudice to minimum wage and statutory rights.</p>

    <h2>7. Social insurance</h2>
    <p class="clause">Employee social insurance number: {{ $social_insurance_number }}. Agreed insurable wage: {{ $social_insurance_salary }} {{ $salary_currency }} per month. The parties acknowledge the insurance number and insurable wage stated in this contract. The Employer shall take registration and contribution steps under applicable Egyptian law. Stating the insurance number in this contract alone is not proof of registration or payment of contributions.</p>

    <h2>8. Leave and statutory rights</h2>
    <p class="clause">The Employee is entitled to leave, weekly rest, public holidays, and all rights under applicable Egyptian law. Nothing in this contract may be interpreted to reduce a mandatory statutory right.</p>

    <h2>9. Obligations</h2>
    <p class="clause">The Employee shall perform duties carefully, follow lawful instructions, and keep confidential information obtained through the role, in accordance with law and published Employer policies. The Employer shall pay agreed wages and benefits, enable the Employee to perform work, and meet legal obligations.</p>

    <h2>10. Termination</h2>
    @if($is_fixed_term)
        <p class="clause">The contract ends when the term expires. Early termination is governed by law and any resulting rights or compensation. A two-month notice period is not presented as a means to terminate a fixed-term contract early without legal consequences.</p>
    @else
        <p class="clause">For an indefinite-term contract, termination requires a legitimate reason and written notice of at least three months, subject to applicable termination rules and procedures.</p>
    @endif
    <p class="clause">Nothing herein affects mandatory employee rights or compulsory legal guarantees and procedures.</p>

    <h2>11. Governing law</h2>
    <p class="clause">This contract is governed by the laws of the Arab Republic of Egypt. Egyptian courts and authorities have jurisdiction over disputes as provided by law.</p>

    <h2>12. Copies and signatures</h2>
    <p class="clause">This contract is drawn up in Arabic in four original copies: one for the Employer, one for the Employee, one for the competent social insurance office, and one for the competent administrative authority, as required by law. The Employer should ensure establishment subscription files are opened with the competent social insurance office where applicable.</p>

    <div class="signatures">
        <p><strong>Employer:</strong><br>
            Name: {{ $signatory_name }}<br>
            Title: {{ $signatory_title }}<br>
            Signature: ____________________<br>
            Date: ____________________</p>
        <p class="sig-line"><strong>Employee:</strong><br>
            Name: {{ $employee_name }}<br>
            Signature: ____________________<br>
            Date: ____________________</p>
    </div>

    @if(!empty($review_notices))
        <div class="review-box">
            <h3>Administrative review — draft only (not part of final contract text)</h3>
            <ul>
                @foreach($review_notices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>
