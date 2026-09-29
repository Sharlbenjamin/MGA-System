<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>مسودة عقد عمل</title>
    @include('pdf.partials.employment-contract-styles-ar')
</head>
<body>
    <div class="draft-banner">
        مسودة (<span dir="ltr" class="ltr-value">DRAFT</span>) — ليس عقداً موقعاً أو معتمداً قانونياً
    </div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="company-block">
                    <div class="name ltr-block">{{ $employer_legal_name }}</div>
                    <div class="ltr-block">{{ $employer_address }}</div>
                    @if(filled($employer_registration))
                        <div>سجل تجاري / تسجيل: @include('pdf.partials.ltr-value', ['text' => $employer_registration])</div>
                    @endif
                    <div>
                        الممثل القانوني:
                        @include('pdf.partials.ltr-value', ['text' => $signatory_name])
                        —
                        @include('pdf.partials.ltr-value', ['text' => $signatory_title])
                    </div>
                </td>
                <td class="logo-cell">
                    @if(!empty($logo_path))
                        <img src="{{ $logo_path }}" width="95" alt="Logo">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="doc-title">عقد عمل فردي عن بُعد — مسودة</div>

    <p>إنه في يوم @include('pdf.partials.ltr-value', ['text' => $draft_date_formatted'])، تم الاتفاق بين كل من:</p>

    <h2>أولاً — صاحب العمل</h2>
    <ul class="party-list">
        <li>الاسم: @include('pdf.partials.ltr-value', ['text' => $employer_legal_name])</li>
        <li>العنوان: @include('pdf.partials.ltr-value', ['text' => $employer_address])</li>
        @if(filled($employer_registration))
            <li>السجل / التسجيل: @include('pdf.partials.ltr-value', ['text' => $employer_registration])</li>
        @endif
        <li>الممثل في التوقيع: @include('pdf.partials.ltr-value', ['text' => $signatory_name]) — @include('pdf.partials.ltr-value', ['text' => $signatory_title])</li>
    </ul>
    <p class="clause">ويشار إليه فيما بعد بـ «صاحب العمل».</p>

    <h2>ثانياً — العامل</h2>
    <ul class="party-list">
        <li>الاسم: @include('pdf.partials.ltr-value', ['text' => $employee_name])</li>
        @if($employee_national_id)
            <li>رقم الهوية: @include('pdf.partials.ltr-value', ['text' => $employee_national_id])</li>
        @endif
        <li>المسمى الوظيفي: @include('pdf.partials.ltr-value', ['text' => $employee_job_title])</li>
    </ul>
    <p class="clause">ويشار إليه/إليها فيما بعد بـ «العامل».</p>

    <h2>تمهيد</h2>
    <p class="clause">اتفق الطرفان على أن يؤدي العامل عملاً لدى صاحب العمل وتحت إدارته أو إشرافه لقاء أجر، وفقاً للشروط التالية وأحكام قانون العمل المصري رقم @include('pdf.partials.ltr-value', ['text' => '14']) لسنة @include('pdf.partials.ltr-value', ['text' => '2025']) وقانون التأمينات الاجتماعية والمعاشات رقم @include('pdf.partials.ltr-value', ['text' => '148']) لسنة @include('pdf.partials.ltr-value', ['text' => '2019']) وتعديلاتهما والقرارات المكملة لهما. ويعد هذا التمهيد جزءاً لا يتجزأ من العقد.</p>

    <h2>1. الوظيفة</h2>
    <p class="clause">يُعيّن صاحب العمل العامل بوظيفة @include('pdf.partials.ltr-value', ['text' => $employee_job_title]). ولا يجوز تكليف العامل بعمل يختلف اختلافاً جوهرياً عن العمل المتفق عليه إلا في الحدود التي يسمح بها القانون.</p>

    <h2>2. مكان ونمط العمل</h2>
    <p class="clause">يؤدى العمل عن بُعد باستخدام وسائل الاتصال والتقنية المتفق عليها. ويلتزم الطرفان بحقوق وواجبات العمل المقررة قانوناً، بما فيها الحماية الاجتماعية والتأمينات، مع مراعاة طبيعة العمل عن بُعد. لا يُفسر العمل عن بُعد على أنه ينتقص من الحماية القانونية للعامل.</p>

    <h2>3. مدة العقد</h2>
    @if($is_fixed_term)
        <p class="clause">هذا العقد محدد المدة من @include('pdf.partials.ltr-value', ['text' => $start_date_formatted]) حتى @include('pdf.partials.ltr-value', ['text' => $end_date_formatted])، لمدة @include('pdf.partials.ltr-value', ['text' => (string) $fixed_term_months]) شهراً، وذلك نظراً لطبيعة العمل/السبب المحدد التالي: {{ $fixed_term_reason }}. وينتهي العقد بانقضاء مدته، ما لم يتفق الطرفان كتابةً على تجديده قبل انتهائه. ولا يجوز الاستمرار في تنفيذ العقد بعد انتهائه دون اتفاق تجديد مكتوب. ويخضع أي إنهاء قبل انتهاء المدة لأحكام القانون.</p>
    @else
        <p class="clause">يبدأ هذا العقد في @include('pdf.partials.ltr-value', ['text' => $start_date_formatted]) ويكون غير محدد المدة.</p>
    @endif

    <h2>4. فترة الاختبار</h2>
    <p class="clause">يخضع العامل لفترة اختبار مدتها شهران تبدأ من تاريخ مباشرة العمل. ولا يجوز تجاوز الحد الأقصى المقرر قانوناً أو إخضاع العامل لفترة اختبار أخرى لدى صاحب العمل ذاته.</p>

    <h2>5. ساعات العمل والراحة</h2>
    <p class="clause">تراعى الحدود القانونية لساعات العمل وفترات الراحة والراحة الأسبوعية والعمل الإضافي وفق قانون العمل المصري، بما في ذلك عدم تجاوز الحدود المعتادة للعمل اليومي والأسبوعي وفترات الراحة الإلزامية.</p>

    <h2>6. الأجر</h2>
    <p class="clause">يتقاضى العامل أجراً إجمالياً شهرياً قدره @include('pdf.partials.ltr-value', ['text' => $full_salary.' '.$salary_currency]). ويُبيّن صاحب العمل مفردات الأجر والاستقطاعات القانونية في بيان الأجر. وتطبق الاشتراكات والاستقطاعات القانونية دون الإخلال بالحد الأدنى للأجر والحقوق المقررة قانوناً.</p>

    <h2>7. التأمين الاجتماعي</h2>
    <p class="clause">الرقم التأميني للعامل: @include('pdf.partials.ltr-value', ['text' => $social_insurance_number]). أجر التأمين المتفق عليه: @include('pdf.partials.ltr-value', ['text' => $social_insurance_salary.' '.$salary_currency]) شهرياً. يقر الطرفان بإدراج الرقم التأميني وأجر التأمين الواردين في بيانات هذا العقد، ويلتزم صاحب العمل باتخاذ إجراءات التسجيل والتأمين وسداد الاشتراكات المستحقة وفقاً للقوانين واللوائح المصرية واجبة التطبيق. ولا يعد ذكر الرقم التأميني في هذا العقد وحده دليلاً على إتمام التسجيل أو سداد الاشتراكات.</p>

    <h2>8. الإجازات والحقوق القانونية</h2>
    <p class="clause">يستحق العامل الإجازات والراحة الأسبوعية والعطلات الرسمية وسائر الحقوق المقررة بموجب القوانين المصرية واجبة التطبيق. ولا يجوز تفسير هذا العقد بما ينتقص من حق إلزامي مقرر للعامل قانوناً.</p>

    <h2>9. التزامات الطرفين</h2>
    <p class="clause">يلتزم العامل بأداء مهامه بعناية واتباع التعليمات المشروعة المتعلقة بالعمل والمحافظة على سرية معلومات صاحب العمل والعملاء التي يطلع عليها بسبب وظيفته، وفقاً للقانون وسياسات صاحب العمل المعلنة. ويلتزم صاحب العمل بسداد الأجر والمزايا المتفق عليها، وتمكين العامل من أداء العمل، والوفاء بالتزاماته القانونية.</p>

    <h2>10. إنهاء العقد</h2>
    @if($is_fixed_term)
        <p class="clause">ينتهي العقد بانقضاء مدته. ويخضع أي إنهاء مبكر لأحكام القانون وما قد يترتب عليه من حقوق وتعويضات. ولا يُستخدم إخطار مدته شهران كوسيلة لإنهاء العقد محدد المدة دون عواقب قانونية.</p>
    @else
        <p class="clause">في العقد غير محدد المدة، يكون الإنهاء بمبرر مشروع وكافٍ وبإخطار كتابي لا تقل مدته عن ثلاثة أشهر، مع مراعاة الأحكام والإجراءات القانونية المتعلقة بالإنهاء.</p>
    @endif
    <p class="clause">ولا يخل أي حكم هنا بحقوق العامل أو الضمانات والإجراءات الآمرة المقررة قانوناً.</p>

    <h2>11. القانون والاختصاص</h2>
    <p class="clause">يخضع هذا العقد لقوانين جمهورية مصر العربية. وتختص الجهات والمحاكم المصرية المختصة بنظر المنازعات الناشئة عنه وفقاً للقانون.</p>

    <h2>12. النسخ والتوقيع</h2>
    <p class="clause">حرر هذا العقد باللغة العربية من أربع نسخ أصلية، تسلم نسخة لصاحب العمل، ونسخة للعامل، وتودع نسخة لدى مكتب التأمين الاجتماعي المختص، ونسخة لدى الجهة الإدارية المختصة، وفقاً للقانون. يجب على صاحب العمل التأكد من فتح ملف اشتراك المنشأة لدى مكتب التأمين الاجتماعي المختص عند الاقتضاء.</p>

    <div class="signatures">
        <p><strong>صاحب العمل:</strong></p>
        <p>الاسم: @include('pdf.partials.ltr-value', ['text' => $signatory_name])<br>
            الصفة: @include('pdf.partials.ltr-value', ['text' => $signatory_title])<br>
            التوقيع: ____________________<br>
            التاريخ: ____________________</p>
        <p class="sig-line"><strong>العامل:</strong></p>
        <p>الاسم: @include('pdf.partials.ltr-value', ['text' => $employee_name])<br>
            التوقيع: ____________________<br>
            التاريخ: ____________________</p>
    </div>

    @php($notices = $review_notices_ar ?? $review_notices ?? [])
    @if(!empty($notices))
        <div class="review-box">
            <h3>ملاحظات مراجعة إدارية — مسودة فقط (ليست جزءاً من نص العقد النهائي)</h3>
            <ul>
                @foreach($notices as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>
