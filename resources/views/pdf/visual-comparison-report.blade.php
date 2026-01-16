<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Visual Comparison Report</title>
    <style>
        @page {
            footer: mainFooter;
        }
        
        body { 
            font-family: DejaVu Sans, Arial, sans-serif; 
            font-size: 13px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        /* Cover Page */
        .cover-page {
            width: 100%;
            height: 100vh;
            display: table;
        }
        
        .cover-content {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding: 20px;
        }
        
        .cover-title {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .cover-subtitle {
            font-size: 20px;
            color: #7f8c8d;
            margin-bottom: 40px;
            font-weight: 600;
        }
        
        .patient-info {
            width: 350px;
            margin: 40px auto;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 25px;
            background: #f9f9f9;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .info-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e8e8e8;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            color: #495057;
            font-size: 15px;
            font-weight: 500;
        }
        
        /* Image Pages */
        /* .image-header {
            text-align: center;
            margin-bottom: 40px;
        }
         */
        .image-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
            text-transform: uppercase;
            margin-bottom: 40px;
            letter-spacing: 1.5px;
            border: 1px solid #e0e0e0;
            padding: 10px;
        }
        
        /* Page Footer */
        .muted {
            color: #6b7280;
            font-size: 10px;
        }
        
        .comparison-image {
            max-width: 100%;
            height: auto;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 3px;
            background: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Cover Page -->
    <div class="cover-page">
        <div class="cover-content">
            <div class="cover-title">AI AESTHETICS</div>
            <div class="cover-subtitle">BEFORE vs AFTER VISUAL COMPARISON REPORT</div>
            
            <div class="patient-info">
                <div class="info-row">
                    <div class="info-label">Patient Name</div>
                    <div class="info-value">{{ $patient->name ?? 'Roshni Taurani' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Age / Gender</div>
                    <div class="info-value">{{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : '—' }} / {{ $patient->gender ?? '—' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Report Date</div>
                    <div class="info-value">{{ date('F j, Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    <htmlpagefooter name="mainFooter">
        <table width="100%" style="font-size: 9pt; border: none; margin-top: 20px;">
            <tr>
                <td align="left" style="border: none;">
                    <span class="muted">AI Aesthetics Visual Report</span>
                </td>
                <td align="right" style="border: none;">
                    <span class="muted">Page {PAGENO} of {nbpg}</span>
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    @php
        // Image order mapping
        $imageOrder = config('project.assessment_image_order');
        
        // Create image maps
        $assessmentImageMap = [];
        foreach ($assessmentImages as $img) {
            $assessmentImageMap[$img['name']] = $img['url'];
        }
        
        $postAssessmentImageMap = [];
        foreach ($postAssessmentImages as $img) {
            $postAssessmentImageMap[$img['name']] = $img['url'];
        }
        
        // Prepare image pages data
        $imagePages = [];
        
        foreach ($imageOrder as $imageType) {
            // Get images for this type
            $beforeImageUrl = $assessmentImageMap[$imageType] ?? null;
            $afterImageUrl = $postAssessmentImageMap[$imageType] ?? null;
            
            // Only include page if there are images
            if ($beforeImageUrl || $afterImageUrl) {
                $imagePages[$imageType] = [
                    'before_image' => $beforeImageUrl,
                    'after_image' => $afterImageUrl,
                ];
            }
        }
    @endphp

    @foreach($imagePages as $imageType => $imageData)
        @php
            $pageClass = str_replace('_', '-', $imageType) . '-page';
            $beforeImageUrl = $imageData['before_image'] ?? null;
            $afterImageUrl = $imageData['after_image'] ?? null;
        @endphp
        
        <div class="page-break"></div>
        
        <div>
            <div>
                <div class="image-header">
                    <div class="image-title">{{ strtoupper($imageType) }}</div>
                </div>

                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center">
                            <p style="font-size: 30px; text-transform: uppercase;">BEFORE (Baseline)</p>
                        </td>

                        <td align="center">
                            <p style="font-size: 30px; text-transform: uppercase;">AFTER (Post)</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align: top; padding-top: 20px;">
                            <img src="{{ $beforeImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image">
                        </td>

                        <td style="vertical-align: top; padding-top: 20px;">
                            <img src="{{ $afterImageUrl ?: public_path('images/no-image.jpg') }}" class="comparison-image">
                        </td>
                    </tr>
                </table>

            </div>
        </div>
    @endforeach

</body>
</html>