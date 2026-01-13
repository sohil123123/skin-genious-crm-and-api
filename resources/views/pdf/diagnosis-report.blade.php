<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>AI Aesthetics Report</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #000;
            line-height: 1.4;
            font-size: 15px;
        }

        /* Define margins and connect headers/footers */
        @page {
            margin-top: 35mm;
            margin-bottom: 20mm;
            margin-left: 15mm;
            margin-right: 15mm;
            header: mainHeader;
            footer: mainFooter;
        }

        /* Header Styling */
        .header-content {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 0px; 
        }
        .header-content h1 {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Footer Styling */
        .footer-content {
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 9px;
            color: #444;
            width: 100%;
        }
        .footer-left { float: left; font-weight: bold; text-transform: uppercase; }
        .footer-right { float: right; font-weight: bold; text-transform: uppercase; }

        /* Content Styling */
        .parameter-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #000;
            text-transform: capitalize;
        }

        .section-label {
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 5px;
            color: #333;
        }

        .section-text {
            font-size: 14px;
            margin-bottom: 30px;
            text-align: justify;
            line-height: 1.5;
        }

        /* Table Grid */
        table.grid-layout {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table.grid-layout td {
            border: 1px solid #000;
            vertical-align: top;
            padding: 15px;
            width: 50%;
        }

        /* Left Column Elements */
        .col-center { text-align: center; }

        .score-circle {
            width: 100px;
            height: 100px; /* Approximate since MPDF sometimes ignores height on block divs if not careful, but table-cell helps */
            /* border: 2px solid #000; */
            border-radius: 50%;
            margin: 0 auto;
            text-align: center;
            /* Flex doesn't work well in MPDF v8, use table-cell vertical align trick */
            border-collapse: collapse;
            border: 1px solid #000;
        }
        
        .score-table {
            width: 100px; 
            height: 100px; 
            margin: 0 auto;
        }
        .score-cell {
            vertical-align: middle;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            line-height: 1.2;
        }
        .text-small { font-size: 12px; }

        .img-box {
            text-align: center;
            margin-top: 10px;
        }

        .separator {
            border-top: 1px solid #000;
            margin: 20px 0;
            width: 100%;
        }

        ul { margin: 0; padding-left: 20px; }
        li { margin-bottom: 5px; }

    </style>
</head>
<body>

    <!-- Defined Header -->
    <htmlpageheader name="mainHeader">
        <div class="header-content">
            <h1>AI AESTHETICS</h1>
        </div>
    </htmlpageheader>

    <!-- Defined Footer -->
    <htmlpagefooter name="mainFooter">
        <table width="100%" style="font-size: 10pt; border: none;">
            <tr>
                <td align="left" style="border: none;">
                    DEVELOPED BY {{config('app.name')}}
                </td>
                <td align="right" style="border: none;">
                    Page {PAGENO} / {nbpg}
                </td>
            </tr>
        </table>
    </htmlpagefooter>

    @php
        $diagnosis = $record->diagnosis ?? [];
        $report = $diagnosis['diagnosis_report'] ?? [];
        
        use Illuminate\Support\Str;

        $imageOrder = config('project.assessment_image_order');

        $sortedImages = [];
        if ($record->images && count($record->images)) {
            foreach ($imageOrder as $key) {
                $found = collect($record->images)->first(function ($img) use ($key) {
                    return Str::contains(Str::lower($img['url']), $key . '.');
                });

                if ($found) {
                    $sortedImages[] = $found;
                }
            }
        }

    @endphp

    @if(empty($report))
        <div style="text-align: center; padding-top: 50px;">
            <p>No diagnosis data available.</p>
        </div>
    @else
        @foreach($report as $key => $data)
            @if(is_array($data))
                
                <!-- Parameter Title -->
                <div class="parameter-title">
                    {{ $data['parameter_name'] ?? ucwords(str_replace('_', ' ', $key)) }}
                </div>

                <!-- Description -->
                <div class="section-label">EXPLANATION OF WHAT THE PARAMETER ENTAILS</div>
                <div class="section-text">
                    {{ $data['description'] ?? 'No description available for this parameter.' }}
                </div>

                <!-- Split Layout Table -->
                <table class="grid-layout">
                    <tr>
                        <!-- Left Column -->
                        <td class="col-center">
                            <div class="section-label">SCORE / TEXT / SKIN TYPE</div>
                            
                            <!-- Score Circle using Nested Table for perfect vertical centering in MPDF -->
                            <div style="margin: 15px 0;">
                                <table class="score-circle">
                                    <tr>
                                        <td class="score-cell">
                                            @php
                                                $score = $data['score_or_label'] ?? '-';
                                                $scoreClass = (strlen($score) > 3) ? 'text-small' : '';
                                            @endphp
                                            <span class="{{ $scoreClass }}">
                                                {{ $score }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <div class="separator"></div>
                            <br>

                            <div class="section-label">FACE IMAGE SHOWING AFFECTED AREAS</div>
                            <div class="img-box">
                                @php
                                    $imgIndex = isset($data['affected_area_image']) ? max(((int)$data['affected_area_image']) - 1, 0) : 0;
                                    $imageSrc = $sortedImages[$imgIndex]['url'] ?? ($sortedImages[0]['url'] ?? null);
                                @endphp
                                
                                @if($imageSrc)
                                    <img src="{{ $imageSrc }}" style="max-width: 100%; max-height: 250px; border: 1px solid #ccc;">
                                @else
                                    <div style="padding: 50px 20px; background: #f5f5f5; color: #999; font-size: 10px; border: 1px dashed #ccc;">
                                        NO IMAGE FOUND
                                    </div>
                                @endif
                            </div>
                        </td>

                        <!-- Right Column -->
                        <td style="text-align: left;">
                            <div class="section-label">EXPLANATION OF SCORE</div>
                            <div class="section-text">
                                {{ $data['score_explanation'] ?? 'No analysis provided.' }}
                            </div>

                            <div class="separator"></div>
                            <br>

                            <div class="section-label">POSSIBLE CAUSES OF THE ISSUE SEEN</div>
                            <div class="section-text">
                                @if(!empty($data['possible_causes']) && is_array($data['possible_causes']))
                                    <ul>
                                        @foreach($data['possible_causes'] as $cause)
                                            <li>{{ $cause }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    No specific causes listed.
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Strict Page Break logic: add break if NOT the last item -->
                @if(!$loop->last)
                    <pagebreak />
                @endif

            @endif
        @endforeach
    @endif
</body>
</html>
