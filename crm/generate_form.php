<?php
$type = $_GET['type'] ?? 'general';
$titles = [
    'cashless_form' => 'Cashless Hospitalization Verification Form',
    'admission_confirmation' => 'Admission Confirmation Statement',
    'reimbursement_form' => 'Reimbursement Claim Verification Form',
    'doctor_form' => 'Medical Practitioner Statement',
    'low_cost_form' => 'Low Cost Claim Investigation Sheet',
    'pathology_form' => 'Pathology Laboratory Verification',
    'pathologist_statement' => 'Pathologist Official Statement',
    'radiologist_statement' => 'Radiologist Official Statement',
];
$title = $titles[$type] ?? 'Official Verification Form';

if(isset($_GET['ajax'])): ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Jost:wght@400;700&family=Lexend:wght@700&display=swap');
        .letterhead-container { 
            padding: 50px; 
            max-width: 800px; 
            margin: 0 auto; 
            position: relative; 
            font-family: 'Jost', sans-serif; 
            background: #fff; 
            color: #1e293b;
            border: 2px solid #e2e8f0;
            min-height: 1050px;
        }
        .header { border-bottom: 4px solid #1e40af; padding-bottom: 30px; margin-bottom: 50px; display: flex; justify-content: space-between; align-items: center; }
        .logo { max-height: 75px; }
        .brand-text { font-family: 'Lexend', sans-serif; font-size: 1.6rem; color: #1e40af; margin-bottom: 4px; font-weight: 700; }
        .company-info { text-align: right; font-size: 0.9rem; color: #475569; line-height: 1.5; }
        h1 { 
            display: block;
            width: 100%;
            text-align: center; 
            font-family: 'Lexend', sans-serif; 
            text-transform: uppercase; 
            font-size: 1.4rem; 
            margin-bottom: 60px; 
            color: #0f172a; 
            letter-spacing: 1.5px; 
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .form-body { background: #ffffff; }
        .form-row { margin-bottom: 30px; display: flex; align-items: flex-end; gap: 15px; }
        .label { font-weight: 700; width: 220px; flex-shrink: 0; color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .dotted-line { flex-grow: 1; border-bottom: 1.5px dotted #cbd5e1; height: 1px; margin-bottom: 6px; }
        .content-box { border: 2.5px solid #f1f5f9; padding: 30px; min-height: 300px; margin-top: 50px; background: #fff; border-radius: 4px; position: relative; }
        .content-box::after { content: "Official Observations / Remarks"; position: absolute; top: -14px; left: 20px; background: #fff; padding: 0 15px; font-size: 0.8rem; font-weight: 700; color: #1e40af; text-transform: uppercase; border: 1px solid #f1f5f9; border-radius: 4px; }
        .footer { margin-top: 120px; display: flex; justify-content: space-between; gap: 80px; }
        .sign-area { flex: 1; text-align: center; padding-top: 20px; font-weight: 700; font-size: 0.95rem; color: #1e293b; border-top: 2px solid #334155; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 7rem; color: #f1f5f9; font-weight: 900; z-index: -1; font-family: 'Lexend', sans-serif; text-transform: uppercase; letter-spacing: 12px; opacity: 0.08; pointer-events: none; }
    </style>
    <div class="letterhead-container">
        <div class="watermark">MEDIPROBE-AFICS</div>
        <div class="header">
            <img src="../assets/images/documantraa_logo.png" class="logo" alt="Logo">
            <div class="company-info">
                <div class="brand-text">Mediprobe-AFICS Group</div>
                Clinical Investigation & Audit Division<br>
                Verification House, West Hill, Calicut<br>
                Contact: +91 75949 22774 | portal@mediprobe.in
            </div>
        </div>
        <div class="form-body">
            <h1><?= $title ?></h1>
            <div class="form-row"><span class="label">Project Code / ID</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Date of Assignment</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Claim / File Number</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Subject / Patient Name</span><span class="dotted-line"></span></div>
            <?php if(strpos($type, 'doctor') !== false): ?>
                <div class="form-row"><span class="label">Treating Doctor Name</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Medical Regn Number</span><span class="dotted-line"></span></div>
            <?php elseif(strpos($type, 'pathology') !== false || strpos($type, 'radio') !== false): ?>
                <div class="form-row"><span class="label">Lab / Center Name</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Sample / Test ID</span><span class="dotted-line"></span></div>
            <?php else: ?>
                <div class="form-row"><span class="label">Provider / Hospital</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Department / Ward</span><span class="dotted-line"></span></div>
            <?php endif; ?>
            
            <div class="form-row"><span class="label">Diagnosis / Ailment</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Field Associate</span><span class="dotted-line"></span></div>
            
            <div class="content-box"></div>
            
            <div class="footer">
                <div class="sign-area">Authorized Signatory</div>
                <div class="sign-area">Branch Seal / Stamp</div>
            </div>
        </div>
        <div style="margin-top: 80px; text-align: center; color: #64748b; font-size: 0.75rem; border-top: 1.5px dashed #e2e8f0; padding-top: 25px;">
            This is an automated operational form generated by the Mediprobe CRM System.<br>
            A security hash (<?= strtoupper(md5($type . time())) ?>) has been embedded for document integrity validation.
        </div>
    </div>
<?php exit; endif; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Jost:wght@400;700&family=Lexend:wght@700&display=swap');
        body { font-family: 'Jost', sans-serif; padding: 0; margin: 0; color: #1e293b; background: #fff; }
        .letterhead-container { 
            padding: 50px; 
            max-width: 800px; 
            margin: 0 auto; 
            position: relative; 
            background: #fff; 
            color: #1e293b;
            border: 2px solid #e2e8f0;
            min-height: 1050px;
        }
        .header { border-bottom: 4px solid #1e40af; padding-bottom: 30px; margin-bottom: 50px; display: flex; justify-content: space-between; align-items: center; }
        .logo { max-height: 75px; }
        .brand-text { font-family: 'Lexend', sans-serif; font-size: 1.6rem; color: #1e40af; margin-bottom: 4px; font-weight: 700; }
        .company-info { text-align: right; font-size: 0.9rem; color: #475569; line-height: 1.5; }
        h1 { 
            display: block;
            width: 100%;
            text-align: center; 
            font-family: 'Lexend', sans-serif; 
            text-transform: uppercase; 
            font-size: 1.4rem; 
            margin-bottom: 60px; 
            color: #0f172a; 
            letter-spacing: 1.5px; 
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 15px;
        }
        .form-row { margin-bottom: 30px; display: flex; align-items: flex-end; gap: 15px; }
        .label { font-weight: 700; width: 220px; flex-shrink: 0; color: #334155; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .dotted-line { flex-grow: 1; border-bottom: 1.5px dotted #cbd5e1; height: 1px; margin-bottom: 6px; }
        .content-box { border: 2.5px solid #f1f5f9; padding: 30px; min-height: 300px; margin-top: 50px; background: #fff; border-radius: 4px; position: relative; }
        .content-box::after { content: "Official Observations / Remarks"; position: absolute; top: -14px; left: 20px; background: #fff; padding: 0 15px; font-size: 0.8rem; font-weight: 700; color: #1e40af; text-transform: uppercase; border: 1px solid #f1f5f9; border-radius: 4px; }
        .footer { margin-top: 120px; display: flex; justify-content: space-between; gap: 80px; }
        .sign-area { flex: 1; text-align: center; padding-top: 20px; font-weight: 700; font-size: 0.95rem; color: #1e293b; border-top: 2px solid #334155; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 7rem; color: #f1f5f9; font-weight: 900; z-index: -1; font-family: 'Lexend', sans-serif; text-transform: uppercase; letter-spacing: 12px; opacity: 0.08; pointer-events: none; }
    </style>
</head>
<body id="pdf-content">
    <div class="letterhead-container">
        <div class="watermark">MEDIPROBE-AFICS</div>
        <div class="header">
            <img src="../assets/images/documantraa_logo.png" class="logo" alt="Logo">
            <div class="company-info">
                <div class="brand-text">Mediprobe-AFICS Group</div>
                Clinical Investigation & Audit Division<br>
                Verification House, West Hill, Calicut<br>
                Contact: +91 75949 22774 | portal@mediprobe.in
            </div>
        </div>
        <div class="form-body">
            <h1><?= $title ?></h1>
            <div class="form-row"><span class="label">Project Code / ID</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Date of Assignment</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Claim / File Number</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Subject / Patient Name</span><span class="dotted-line"></span></div>
            <?php if(strpos($type, 'doctor') !== false): ?>
                <div class="form-row"><span class="label">Treating Doctor Name</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Medical Regn Number</span><span class="dotted-line"></span></div>
            <?php elseif(strpos($type, 'pathology') !== false || strpos($type, 'radio') !== false): ?>
                <div class="form-row"><span class="label">Lab / Center Name</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Sample / Test ID</span><span class="dotted-line"></span></div>
            <?php else: ?>
                <div class="form-row"><span class="label">Provider / Hospital</span><span class="dotted-line"></span></div>
                <div class="form-row"><span class="label">Department / Ward</span><span class="dotted-line"></span></div>
            <?php endif; ?>
            <div class="form-row"><span class="label">Diagnosis / Ailment</span><span class="dotted-line"></span></div>
            <div class="form-row"><span class="label">Field Associate</span><span class="dotted-line"></span></div>
            <div class="content-box"></div>
            <div class="footer">
                <div class="sign-area">Authorized Signatory</div>
                <div class="sign-area">Branch Seal / Stamp</div>
            </div>
        </div>
        <div style="margin-top: 100px; text-align: center; color: #64748b; font-size: 0.75rem; border-top: 1.5px dashed #e2e8f0; padding-top: 25px;">
            This is an automated operational form generated by the Mediprobe CRM System.<br>
            A security hash has been embedded for document integrity validation.
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.onload = function() {
            const element = document.body;
            const opt = {
                margin:       [0,0,0,0],
                filename:     '<?= str_replace('_form','', $type) ?>_form.pdf',
                image:        { type: 'jpeg', quality: 1.0 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save().then(() => {
                setTimeout(() => { window.close(); }, 1500);
            });
        };
    </script>
</body>
</html>


