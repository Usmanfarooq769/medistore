<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Starter list of well-documented drug-drug interactions (single ingredient names, lower case).
 * It is a reference aid only – the pharmacist must always confirm. Add / edit rows in the app
 * (Quality & Compliance -> Drug Interactions) to match your formulary.
 */
class DrugInteractionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // [a, b, severity, effect, management]
            ['warfarin', 'aspirin', 'major', 'Higher risk of serious bleeding.', 'Avoid unless prescribed; monitor INR and bleeding.'],
            ['warfarin', 'ibuprofen', 'major', 'Higher risk of bleeding and stomach ulcers.', 'Avoid NSAIDs; use paracetamol if suitable.'],
            ['warfarin', 'diclofenac', 'major', 'Higher risk of bleeding and stomach ulcers.', 'Avoid NSAIDs; use paracetamol if suitable.'],
            ['warfarin', 'naproxen', 'major', 'Higher risk of bleeding.', 'Avoid combination; monitor INR.'],
            ['warfarin', 'metronidazole', 'major', 'INR can rise sharply (more bleeding).', 'Monitor INR closely; dose change may be needed.'],
            ['warfarin', 'fluconazole', 'major', 'INR can rise sharply (more bleeding).', 'Monitor INR closely; dose change may be needed.'],
            ['warfarin', 'ciprofloxacin', 'moderate', 'May increase INR and bleeding risk.', 'Monitor INR during and after the course.'],
            ['warfarin', 'amiodarone', 'major', 'Strongly increases warfarin effect.', 'Reduce warfarin dose; monitor INR.'],
            ['clopidogrel', 'omeprazole', 'moderate', 'May reduce the antiplatelet effect of clopidogrel.', 'Prefer pantoprazole if a PPI is needed.'],
            ['aspirin', 'ibuprofen', 'moderate', 'Ibuprofen can block the heart-protective effect of low-dose aspirin.', 'Take aspirin at least 30 min before ibuprofen, or avoid.'],
            ['aspirin', 'diclofenac', 'moderate', 'More stomach bleeding; may reduce aspirin benefit.', 'Avoid regular combined use.'],
            ['simvastatin', 'clarithromycin', 'major', 'Risk of muscle damage (rhabdomyolysis).', 'Stop statin during the antibiotic course or choose another antibiotic.'],
            ['atorvastatin', 'clarithromycin', 'major', 'Risk of muscle damage (rhabdomyolysis).', 'Pause statin during the course or choose another antibiotic.'],
            ['digoxin', 'clarithromycin', 'major', 'Digoxin level can rise to toxic range.', 'Monitor digoxin level; consider alternative.'],
            ['sildenafil', 'isosorbide', 'contraindicated', 'Severe drop in blood pressure.', 'Do not combine nitrates with sildenafil.'],
            ['sildenafil', 'nitroglycerin', 'contraindicated', 'Severe drop in blood pressure.', 'Do not combine nitrates with sildenafil.'],
            ['tadalafil', 'isosorbide', 'contraindicated', 'Severe drop in blood pressure.', 'Do not combine nitrates with tadalafil.'],
            ['tadalafil', 'nitroglycerin', 'contraindicated', 'Severe drop in blood pressure.', 'Do not combine nitrates with tadalafil.'],
            ['tramadol', 'sertraline', 'major', 'Risk of serotonin syndrome and seizures.', 'Avoid or use only under close medical supervision.'],
            ['tramadol', 'fluoxetine', 'major', 'Risk of serotonin syndrome and seizures.', 'Avoid or use only under close medical supervision.'],
            ['diazepam', 'tramadol', 'major', 'Extra sedation and breathing depression.', 'Avoid; if essential use lowest doses.'],
            ['diazepam', 'codeine', 'major', 'Extra sedation and breathing depression.', 'Avoid; if essential use lowest doses.'],
            ['alprazolam', 'tramadol', 'major', 'Extra sedation and breathing depression.', 'Avoid; if essential use lowest doses.'],
            ['clonazepam', 'tramadol', 'major', 'Extra sedation and breathing depression.', 'Avoid; if essential use lowest doses.'],
            ['methotrexate', 'trimethoprim', 'major', 'Serious bone-marrow toxicity.', 'Avoid combination.'],
            ['methotrexate', 'cotrimoxazole', 'major', 'Serious bone-marrow toxicity.', 'Avoid combination.'],
            ['methotrexate', 'ibuprofen', 'moderate', 'NSAIDs can raise methotrexate levels.', 'Monitor, especially with high-dose methotrexate.'],
            ['ciprofloxacin', 'theophylline', 'major', 'Theophylline toxicity (fits, fast heartbeat).', 'Monitor level; reduce theophylline dose.'],
            ['ciprofloxacin', 'aluminium hydroxide', 'moderate', 'Antacid blocks absorption of the antibiotic.', 'Give ciprofloxacin 2 h before or 6 h after the antacid.'],
            ['ciprofloxacin', 'calcium', 'moderate', 'Calcium blocks absorption of the antibiotic.', 'Separate doses by at least 2 hours.'],
            ['doxycycline', 'calcium', 'moderate', 'Calcium reduces antibiotic absorption.', 'Separate doses by at least 2-3 hours.'],
            ['tetracycline', 'calcium', 'moderate', 'Calcium reduces antibiotic absorption.', 'Separate doses by at least 2-3 hours.'],
            ['levothyroxine', 'calcium', 'moderate', 'Calcium reduces thyroid-tablet absorption.', 'Separate doses by at least 4 hours.'],
            ['levothyroxine', 'iron', 'moderate', 'Iron reduces thyroid-tablet absorption.', 'Separate doses by at least 4 hours.'],
            ['spironolactone', 'lisinopril', 'moderate', 'Potassium can become too high.', 'Monitor potassium and kidney function.'],
            ['spironolactone', 'enalapril', 'moderate', 'Potassium can become too high.', 'Monitor potassium and kidney function.'],
            ['spironolactone', 'potassium chloride', 'major', 'Dangerously high potassium.', 'Avoid unless monitored by a doctor.'],
            ['lisinopril', 'ibuprofen', 'moderate', 'NSAIDs reduce BP control and can harm kidneys.', 'Prefer paracetamol; monitor BP and kidneys.'],
            ['enalapril', 'ibuprofen', 'moderate', 'NSAIDs reduce BP control and can harm kidneys.', 'Prefer paracetamol; monitor BP and kidneys.'],
            ['lisinopril', 'diclofenac', 'moderate', 'NSAIDs reduce BP control and can harm kidneys.', 'Prefer paracetamol; monitor BP and kidneys.'],
            ['lithium', 'ibuprofen', 'major', 'Lithium can reach toxic levels.', 'Avoid NSAIDs; monitor lithium level.'],
            ['lithium', 'diclofenac', 'major', 'Lithium can reach toxic levels.', 'Avoid NSAIDs; monitor lithium level.'],
            ['sertraline', 'aspirin', 'moderate', 'Higher bleeding risk.', 'Use with caution; watch for bruising or bleeding.'],
            ['fluoxetine', 'tramadol', 'major', 'Risk of serotonin syndrome.', 'Avoid combination.'],
            ['clarithromycin', 'colchicine', 'major', 'Colchicine toxicity.', 'Avoid combination, especially in kidney disease.'],
            ['amlodipine', 'simvastatin', 'moderate', 'Simvastatin level rises; muscle-pain risk.', 'Keep simvastatin dose at 20 mg or lower.'],
            ['theophylline', 'erythromycin', 'major', 'Theophylline toxicity.', 'Monitor level or choose another antibiotic.'],
        ];

        $now = now();
        foreach ($rows as [$a, $b, $sev, $effect, $mgmt]) {
            if ($a > $b) { [$a, $b] = [$b, $a]; }   // store pairs alphabetically
            DB::table('drug_interactions')->updateOrInsert(
                ['ingredient_a' => $a, 'ingredient_b' => $b],
                ['severity' => $sev, 'effect' => $effect, 'management' => $mgmt, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
