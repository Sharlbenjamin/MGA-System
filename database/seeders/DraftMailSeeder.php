<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DraftMail;

class DraftMailSeeder extends Seeder
{
    public function run()
    {
        $draftMails = [
            [
                'mail_name' => "Introducing MedGuard: {company}’s Trusted Partner in Travel Assistance & Telemedicine",
                'body_mail' => "Dear {name},
                                
                                I hope this email finds you well. I’m thrilled to introduce MedGuard, a company committed to providing seamless travel assistance and reliable telemedicine services, all designed with your convenience in mind.
                                
                                Key highlights of our services:
                                • <strong>Telemedicine (25€) in English</strong>: Immediate access to licensed medical professionals who speak <strong>English</strong>, ensuring clear and effective communication, no matter where you are.
                                • <strong>Travel Assistance</strong>: 24/7 support for emergencies during your travels.
                                • Tailored solutions to meet your specific needs, whether for individuals or businesses.
                                
                                We understand the importance of having trustworthy support while traveling or accessing healthcare remotely. At <strong>MedGuard</strong>, we’re here to give you peace of mind wherever you go.
                                
                                I’d love to discuss how <strong>MedGuard</strong> can support you or your organization. Please feel free to reply to this email to schedule a call.
                                
                                Warm Regards,",
                'status' => 'Introduction',
                'type' => 'Client',
                'new_status' => 'Introduction Sent',
            ],
            [
                'mail_name' => "Med Guard's Price List and Coverage Area",
                'body_mail' => "Dear {name},
                                
                                Price list for <strong> House Call </strong> services: (House Call Services are available in the rest of the cities in these countries depending on the city and time of the request)
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;<strong>•Spain</strong>: 
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1.<strong>Madrid & Barcelona</strong> : <strong>100€</strong> Including our File Fees.
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2.All Spain : <strong>195€</strong> Including File Fees.
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;<strong>•The UK</strong>: 
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1.<strong>London</strong> : <strong>165€</strong> Including our File Fees.
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2.All UK : <strong>300€</strong> Including File Fees.
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;<strong>•France</strong>: 
                                
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;1.<strong>Paris</strong> : <strong>170€ - 220€</strong> Including our File Fees.
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2.All France : <strong>300€</strong> Including File Fees.
                                
                                <strong>File Fees </strong> for the rest of the <strong>Assistance Services</strong>:
                                &nbsp;&nbsp;&nbsp;&nbsp;<strong>•Simple File Fees: 50€
                                &nbsp;&nbsp;&nbsp;&nbsp;•Inpatient / Multiple File Fees Cases: 90€</strong>
                                
                                <strong>Coverage area</strong> where our <strong>GOP</strong> is accepted:
                                <strong>&nbsp;&nbsp;&nbsp;&nbsp;Spain</strong>: <strong>Madrid, Barcelona</strong>, Valencia, Málaga, Zaragoza, Sevilla, Murcia, Marbella, Malaga <strong>Islas Baleares, Islas Canarias</strong>.
                                <strong>&nbsp;&nbsp;&nbsp;&nbsp;The UK</strong>: <strong>Londong</strong>, Manchester, Liverpool, <strong>Dublin</strong>, Edinburgh, Birmingham.
                                <strong>&nbsp;&nbsp;&nbsp;&nbsp;France</strong>: <strong>Paris</strong>, Lyonn, Nantes, Toulouse, Cannes, Nice, <strong>Alpes</strong>.
                                
                                Warm Regards,",
                'status' => 'Price List',
                'type' => 'Client',
                'new_status' => 'Price List Sent',
            ],
            [
                'mail_name' => "Follow up on Med Guard's Services",
                'body_mail' => "Dear {name},
                                
                                I hope you’re doing well. 
                                
                                We are excited to start receiving cases from you. I wanted to follow up on my previous email introducing <strong>MedGuard</strong>, our travel assistance and telemedicine services tailored for your convenience.
                                
                                I completely understand that things get busy, but I truly believe our services could bring value to you or your organization.
                                Here’s a quick recap:
                                <strong>&nbsp;&nbsp;&nbsp;&nbsp;• 24/7 Travel Assistance</strong>: Reliable support during emergencies on the go.
                                <strong>&nbsp;&nbsp;&nbsp;&nbsp;• Telemedicine (25€) in English</strong>: Instant access to licensed, English-speaking medical professionals.
                                
                                We’re passionate about delivering peace of mind during travel and ensuring access to dependable healthcare services, no matter where you are.
                                
                                If you’re interested, I’d love the chance to discuss how MedGuard can support you. Feel free to reply to this email or let me know a convenient time for a quick call.
                                
                                Looking forward to connecting!
                                
                                Warm Regards,",
                'status' => 'Reminder',
                'type' => 'Client',
                'new_status' => 'Reminder Sent',
            ],
            [
                'mail_name' => "Medguard's Presentation",
                'body_mail' => "Dear {name},
                                
                                I hope this email finds you well. 
                                
                                Please click on this link for our online Presentation :- https://docs.google.com/presentation/d/1_ZZWj_9fHiBftf1UjQbdL_NyARsosojQBMEQBZuGfO8/edit?usp=sharing
                                
                                If you have any points you’d like to discuss, feel free to email me to arrange an <strong>online meeting</strong>.
                                
                                If you’re interested in sending cases, let me know so we can provide our <strong>draft contract</strong> for your review. Alternatively, if you have a draft, we’d be happy to review it and provide feedback.
                                
                                Warm Regards,",
                'status' => 'Presentation',
                'type' => 'Client',
                'new_status' => 'Presentation Sent',
            ],
            [
                'mail_name' => "MedGuard Assistance Contract for Your Review & Signature",
                'body_mail' => "Dear {name},
                                
                                I hope you’re doing well. 
                                
                                Please click here for the contract:- https://docs.google.com/document/d/1ImJOp4QPytuOfeAijVACQs7laO-p-l6Y1jQZvmSMK-8/edit?usp=sharing
                                
                                Kindly review, fill in the necessary details, sign, and return a scanned copy at your earliest convenience.
                                
                                Feel free to reach out if you have any questions, and we can arrange a meeting to go over the details. Looking forward to working together!
                                
                                Warm Regards,",
                'status' => 'Contract',
                'type' => 'Client',
                'new_status' => 'Contract Sent',
            ],
        ];

        foreach ($draftMails as $draftMail) {
            DraftMail::updateOrCreate(
                ['mail_name' => $draftMail['mail_name']], // Avoid duplicate seeding
                $draftMail
            );
        }
    }
}