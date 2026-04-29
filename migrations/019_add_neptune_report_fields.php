<?php
// Migration 019: Add Neptune Investigation Report Fields
// Adds all columns required by the full Neptune-style investigation form
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for Neptune-style investigation report...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        // ── Insured & Related Details (detailed section) ──────────────────
        'claim_type'               => "VARCHAR(100) NULL COMMENT 'e.g. Reimbursement, Cashless'",
        'patient_relation'         => "VARCHAR(100) NULL COMMENT 'Relation of patient with insured (Self, Spouse, etc)'",
        'insured_occupation'       => "VARCHAR(255) NULL COMMENT 'Occupation / Corporate of the insured'",
        'residence_distance'       => "VARCHAR(255) NULL COMMENT 'How far is insured residence from hospital'",
        'hospital_selection_reason'=> "TEXT NULL COMMENT 'Reason for selecting this particular hospital'",
        'other_hospitals_nearby'   => "VARCHAR(10) NULL COMMENT 'Any good hospitals nearby (Yes/No)'",
        'family_physician'         => "VARCHAR(255) NULL COMMENT 'Family physician / first consulting doctor'",
        'other_policies'           => "VARCHAR(255) NULL COMMENT 'Other policies with US & other insurers'",
        'earlier_claims'           => "TEXT NULL COMMENT 'Earlier claims from us and other insurers'",

        // ── Hospital & Doctor Related ────────────────────────────────────
        'hospital_beds'            => "VARCHAR(50) NULL COMMENT 'No. of in-patient beds'",
        'hospital_reg_no'          => "VARCHAR(100) NULL COMMENT 'Hospital registration number'",
        'hospital_ot'              => "VARCHAR(10) NULL COMMENT 'Has operation theater? (Yes/No)'",
        'hospital_facilities'      => "TEXT NULL COMMENT 'Pathology, pharmacy, basic facilities'",
        'doctor_qualification'     => "VARCHAR(255) NULL COMMENT 'Qualification of treating doctor'",
        'treating_doctor'          => "VARCHAR(255) NULL COMMENT 'Name of treating doctor'",
        'room_rent_tariff'         => "VARCHAR(10) NULL COMMENT 'Different room rent tariff options available (Yes/No)'",

        // ── Disease Related Claims ───────────────────────────────────────
        'main_complaints'          => "TEXT NULL COMMENT 'Main complaints on admission'",
        'scar_mark_verification'   => "VARCHAR(255) NULL COMMENT 'Scar mark verification result'",
        'treatment_line'           => "VARCHAR(255) NULL COMMENT 'Line of treatment – Medical or Surgical'",
        'surgeon_name'             => "VARCHAR(255) NULL COMMENT 'Name of surgeon and anesthetist'",
        'stay_justified'           => "VARCHAR(255) NULL COMMENT 'Is stay justified / prolonged?'",
        'previous_history'         => "VARCHAR(10) NULL COMMENT 'Previous history of similar complaints (Yes/No)'",
        'diagnostics_in_line'      => "VARCHAR(10) NULL COMMENT 'Diagnostic tests in line with diagnosis (Yes/No)'",

        // ── Accident Related Claims ──────────────────────────────────────
        'accident_datetime'        => "VARCHAR(100) NULL COMMENT 'Exact date and time of accident/injury'",
        'accident_narration'       => "TEXT NULL COMMENT 'Narration of how incident happened'",
        'pa_policy'                => "VARCHAR(10) NULL COMMENT 'PA policy with us or other insurer (Yes/No/NA)'",
        'alcoholism_noted'         => "VARCHAR(10) NULL COMMENT 'Any alcoholism factor noted (Yes/No/NA)'",
        'accident_type'            => "VARCHAR(100) NULL COMMENT 'Accident / Assault / Suicidal Attempt'",

        // ── Verification from Hospital ───────────────────────────────────
        'indoor_register_verified' => "VARCHAR(10) NULL COMMENT 'Indoor register verified for insured entry (Yes/No)'",
        'overwritten_dates'        => "VARCHAR(10) NULL COMMENT 'Any overwritten dates in IPD (Yes/No)'",
        'ipd_single_stretch'       => "VARCHAR(10) NULL COMMENT 'IPD papers written in single stretch (Yes/No)'",

        // ── Verification from Medical Store ─────────────────────────────
        'medicine_matches_bills'   => "VARCHAR(10) NULL COMMENT 'Medicines in IPD match medical bills (Yes/No)'",
        'bill_inflation'           => "VARCHAR(10) NULL COMMENT 'Any bill inflation noted (Yes/No)'",

        // ── Verification from Lab ────────────────────────────────────────
        'lab_register_verified'    => "VARCHAR(100) NULL COMMENT 'Lab register verified for insured entry'",
        'lab_bill_inflation'       => "VARCHAR(10) NULL COMMENT 'Lab bill inflation (Yes/No)'",

        // ── Observation / Comments (free-text) ──────────────────────────
        'icp_observation'          => "TEXT NULL COMMENT 'Observation – ICP / visit to hospital narrative'",
        'patient_observation'      => "TEXT NULL COMMENT 'Observation – Patient part narrative'",
        'doctor_observation'       => "TEXT NULL COMMENT 'Observation – Treating doctor part narrative'",
        'investigator_comments'    => "TEXT NULL COMMENT 'Investigator comments and advice'",
        'admission_genuinely'      => "VARCHAR(100) NULL COMMENT 'Admission genuinely (Cashless / Reimbursement)'",
        'patient_paid_amount'      => "VARCHAR(100) NULL COMMENT 'Amount paid by patient'",
        'amount_confirmation'      => "VARCHAR(255) NULL COMMENT 'Amount confirmation details'",
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        } else {
            echo " - Skipped $col (already exists)\n";
        }
    }

    echo "\nMigration 019 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
