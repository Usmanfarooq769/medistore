<?php

namespace Database\Seeders;

use App\Models\ProductRoadmapMilestone;
use Illuminate\Database\Seeder;

class ProductRoadmapSeeder extends Seeder
{
    public function run(): void
    {
        $milestones = [
            [1,'foundation-core','Pharmacy Core & Foundation','Medicine catalogue, purchasing, inventory, POS, users and core reporting.','completed',100],
            [2,'professional-operations','Professional Pharmacy Operations','Batch management, returns, cashier controls, accounting and operational workflows.','completed',100],
            [3,'customer-intelligence','Customer, Loyalty & Sales Intelligence','Customer credit, loyalty, promotions, receivables and demand suggestions.','completed',100],
            [4,'chain-healthcare','Chain & Healthcare Expansion','Multi-branch management, doctors, clinics, schemes, expiry claims and insurance foundations.','completed',100],
            [5,'enterprise-integration','Enterprise Integration & Compliance','Procurement, warehouse, e-prescriptions, regulatory registers, analytics and campaigns.','completed',100],
            [6,'advanced-fulfilment','Advanced Operations & Fulfilment','Online order requests, delivery operations, cold-chain records and supplier contracts.','completed',100],
            [7,'quality-governance','Quality, Safety & Governance','Medication safety, corrective actions, controlled SOPs, compliance audits and product roadmap governance.','completed',100],
            [8,'ecosystem-expansion','Ecosystem & Partner Expansion','Customer self-service, partner APIs, signed webhooks, subscription records and mobile PWA workflows.','planned',0],
            [9,'production-readiness','Production Readiness & Launch Assurance','Security assessment, performance testing, disaster recovery drills, monitoring, UAT and operational sign-off.','planned',0],
        ];

        foreach ($milestones as [$number,$code,$title,$description,$status,$progress]) {
            ProductRoadmapMilestone::firstOrCreate(
                ['milestone_number'=>$number],
                ['milestone_code'=>$code,'title'=>$title,'description'=>$description,'status'=>$status,'progress_percent'=>$progress]
            );
        }

        $tasks = [
            7 => [
                'Medication safety incident reporting and review workflow',
                'Corrective and preventive action tracking with verification',
                'Controlled SOP and policy document version approval',
                'Branch compliance audits and corrective findings',
                'Product roadmap progress and remaining-work dashboard',
            ],
            8 => [
                'Customer self-service portal with invitation-based accounts and online orders',
                'Partner API clients, scopes, rate limits and request audit logs',
                'Signed partner webhooks with delivery retries and event logs',
                'Branch subscription plans, invoices and administrative payment records',
                'Notification center and installable mobile PWA workflows',
                'Native Android/iOS applications and live payer/hospital vendor integrations (future work)',
            ],
            9 => [
                'System health snapshots for database, cache, storage, queue and backup freshness',
                'Security event logging for authentication and partner API access',
                'Deployment release register and rollback documentation',
                'Disaster recovery drill register and isolated restore verification',
                'Independent penetration testing and load testing by qualified reviewers',
                'User acceptance testing, monitoring, alerting and production go-live sign-off',
            ],
        ];

        foreach ($tasks as $number => $titles) {
            $milestone = ProductRoadmapMilestone::where('milestone_number',$number)->first();
            if (!$milestone) continue;
            foreach ($titles as $index => $title) {
                $task = $milestone->tasks()->firstOrCreate(
                    ['title'=>$title],
                    ['status'=>'planned','sort_order'=>$index + 1]
                );
                if ($number === 7) $task->update(['status'=>'completed','completed_at'=>$task->completed_at ?: now()]);
            }
        }

        ProductRoadmapMilestone::where('milestone_number', 7)->update(['status'=>'completed','progress_percent'=>100,'completed_at'=>now()]);
        ProductRoadmapMilestone::where('milestone_number', 8)->update(['milestone_code'=>'ecosystem-expansion','title'=>'Ecosystem & Partner Expansion','description'=>'Customer self-service, partner APIs, signed webhooks, subscription records and mobile PWA workflows.']);
    }
}
