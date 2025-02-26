<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DraftMail;

class ProviderDraftMailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $draftMails = [
            [
                'mail_name' => "Request for Partnership with {name} for {service} in {city}",
                'body_mail' => "Dear {name},
                                
                                I hope this message finds you well. My name is <strong>Juan Fernando</strong>, The <strong>Provider Network Manager</strong> of <strong>MedGuard</strong>, a company specializing in travel assistance.
                                
                                We are expanding our network in <strong>{city}</strong> and are looking for esteemed professionals like yourself to partner with for <strong>{service}</strong>. Based on our current needs, there is a possibility of handling up to <strong>50 cases per month</strong>, offering a steady stream of patients requiring your expertise.
                                
                                If you are interested please update us with the type of services that you or your medical facility can provide for us (House Call, Clinic Visit, Telemedicine, Online Prescription). with price list if possible. and we will be in touch with you for a quick chat to discuss the availability
                                
                                Thank you for considering this partnership. I look forward to your response!",
                'status' => 'Step One',
                'type' => 'Provider',
                'new_status' => 'Step One Sent',
            ],
            [
                'mail_name' => "Request for Cost Adjustment",
                'body_mail' => "Dear {name},
                                
                                I hope this message finds you well.
                                
                                We are delighted to welcome you to our network of doctors and are excited about the opportunity to start collaborating by referring patients to you in {city}.
                                
                                We would like to inform you that other providers (Doctors/Agencies) in {city} are offering us more competitive rates. To enhance our partnership and<strong> significantly increase the volume of cases </strong> we refer to you, we kindly request you to consider adjusting your rates. This would enable us to prioritize sending you a steady flow of patients rather than limiting referrals to urgent or last-resort cases.
                                
                                We look forward to hearing your thoughts.",
                'status' => 'Discount',
                'type' => 'Provider',
                'new_status' => 'Discount Sent',
            ],
            [
                'mail_name' => "Request for Partnership with {name} for {service} in {city}",
                'body_mail' => "Dear {name},
                                
                                I hope this email finds you well. I’m thrilled to introduce MedGuard, a company committed to providing seamless travel assistance and reliable telemedicine services, all designed with your convenience in mind.
                                
                                Key highlights of our services:
                                • <strong>Telemedicine (25€) in English</strong>: Immediate access to licensed medical professionals who speak <strong>English</strong>, ensuring clear and effective communication, no matter where you are.
                                • <strong>Travel Assistance</strong>: 24/7 support for emergencies during your travels.
                                • Tailored solutions to meet your specific needs, whether for individuals or businesses.
                                
                                We understand the importance of having trustworthy support while traveling or accessing healthcare remotely. At <strong>MedGuard</strong>, we’re here to give you peace of mind wherever you go.
                                
                                I’d love to discuss how <strong>MedGuard</strong> can support you or your organization. Please feel free to reply to this email to schedule a call.",
                'status' => 'Discount',
                'type' => 'Provider',
                'new_status' => 'Discount Sent',
            ],
            [
                'mail_name' => "Request for Partnership with {name} for {service} in {city}",
                'body_mail' => "Dear {name},
                                
                                We have updated your price list and services in our records. As a next step, we would like to inquire if you would be interested in signing our online contract, which we offer to all our providers. This contract serves to clarify payment terms and conditions while ensuring the confidentiality of our network and patients. 
                                
                                If you are interested, please let us know so we can send the contract for your review.
                                
                                Additionally, we would appreciate it if you could inform us of your preferred method of communication for receiving patient details and location information from us.
                                
                                I look forward to your response!",
                'status' => 'Step Two',
                'type' => 'Provider',
                'new_status' => 'Step Two Sent',
            ],
            [
                'mail_name' => "Friendly Reminder: Partnership Opportunity with MedGuard Assistance",
                'body_mail' => "Dear {name},
                                
                                I hope this message finds you well. Last week, I reached out regarding a potential partnership opportunity with MedGuard, where we aim to collaborate with esteemed professionals like yourself in {city} for {service}.
                                
                                We are keen to explore the possibility of working together to assist up to 50 cases per month, providing a steady stream of patients requiring your expertise.
                                
                                If you are interested, we kindly ask that you update us regarding the services you or your medical facility can provide (e.g., House Call, Clinic Visit, Telemedicine, Online Prescription), along with a price list if possible.
                                
                                We would love the opportunity to discuss further and finalize this collaboration. Please feel free to reply to this email or reach out if you have any questions.

                                Thank you for considering this partnership. I look forward to your response!",
                'status' => 'Reminder',
                'type' => 'Provider',
                'new_status' => 'Reminder Sent',
            ],
            [
                'mail_name' => "Med Guard Assistance Presntation",
                'body_mail' => "Dear {name},
                                
                                I hope this message finds you well.
                                
                                We are excited to start sending you cases. please take the time to take a look into our <a href='https://docs.google.com/presentation/d/1x8qMHINpYHWv9QGISdr5Wgbd0SqubxvtjqQvCkUd8o8/edit?usp=sharing'><strong>presentaiton</strong></a> to clarify more how we work.
                                
                                We would love the opportunity to discuss further and finalize this <strong>collaboration</strong>. Please feel free to reply to this email or reach out if you have any questions.
                                
                                Thank you for your time and consideration. I look forward to hearing from you soon!",
                'status' => 'Presentation',
                'type' => 'Provider',
                'new_status' => 'Presentation Sent',
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
