<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "<h1>Database Update Process</h1>";
    echo "<pre>Checking projects table for missing columns...\n\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'patient_relation'         => "VARCHAR(255) NULL",
        'insured_occupation'       => "VARCHAR(255) NULL",
        'residence_distance'       => "VARCHAR(255) NULL",
        'hospital_selection_reason'=> "TEXT NULL",
        'other_hospitals_nearby'   => "VARCHAR(255) NULL",
        'family_physician'         => "VARCHAR(255) NULL",
        'other_policies'           => "TEXT NULL",
        'earlier_claims'           => "TEXT NULL",
        'hospital_beds'            => "VARCHAR(100) NULL",
        'hospital_reg_no'          => "VARCHAR(255) NULL",
        'hospital_ot'              => "VARCHAR(50) NULL",
        'room_rent_tariff'         => "VARCHAR(255) NULL",
        'hospital_facilities'      => "TEXT NULL",
        'doctor_qualification'     => "VARCHAR(255) NULL",
        'treating_doctor'          => "VARCHAR(255) NULL",
        'scar_mark_verification'   => "VARCHAR(255) NULL",
        'treatment_line'           => "VARCHAR(255) NULL",
        'surgeon_name'             => "VARCHAR(255) NULL",
        'stay_justified'           => "VARCHAR(255) NULL",
        'previous_history'         => "VARCHAR(100) NULL",
        'diagnostics_in_line'      => "VARCHAR(100) NULL",
        'accident_datetime'        => "VARCHAR(255) NULL",
        'accident_narration'       => "TEXT NULL",
        'pa_policy'                => "VARCHAR(100) NULL",
        'alcoholism_noted'         => "VARCHAR(100) NULL",
        'accident_type'            => "VARCHAR(255) NULL",
        'indoor_register_verified' => "VARCHAR(100) NULL",
        'overwritten_dates'        => "VARCHAR(100) NULL",
        'ipd_single_stretch'       => "VARCHAR(100) NULL",
        'medicine_matches_bills'   => "VARCHAR(100) NULL",
        'bill_inflation'           => "VARCHAR(100) NULL",
        'lab_register_verified'    => "VARCHAR(100) NULL",
        'lab_bill_inflation'       => "VARCHAR(100) NULL",
        'icp_observation'          => "TEXT NULL",
        'patient_observation'      => "TEXT NULL",
        'doctor_observation'       => "TEXT NULL",
        'investigator_comments'    => "TEXT NULL",
        'admission_genuinely'      => "VARCHAR(100) NULL",
        'patient_paid_amount'      => "VARCHAR(100) NULL",
        'amount_confirmation'      => "VARCHAR(255) NULL",
        'closure_conclusion'       => "TEXT NULL",
        'investigator_phone'       => "VARCHAR(100) NULL",
        'investigator_email'       => "VARCHAR(150) NULL",
        'claim_type'               => "VARCHAR(100) NULL",
        'main_complaints'          => "TEXT NULL"
    ];

    $added_count = 0;
    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo "[SUCCESS] Added missing column: $col\n";
            $added_count++;
        }
    }

    if ($added_count === 0) {
        echo "[OK] No columns were missing. Your database is fully up to date!\n";
    } else {
        echo "\n[SUCCESS] Total $added_count columns added.\n";
    }
    echo "</pre>";

} catch (PDOException $e) {
    die("<h2 style='color:red;'>Database Error:</h2><pre>" . $e->getMessage() . "</pre>");
}
?>
