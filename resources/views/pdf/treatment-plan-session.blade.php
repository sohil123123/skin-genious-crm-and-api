<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Treatment Plan Session</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 13px;
            color: #1f2937;
        }

        /* Define margins and connect headers/footers */
        @page {
            /* margin-top: 35mm;
            margin-bottom: 20mm;
            margin-left: 15mm;
            margin-right: 15mm; */
            /* header: mainHeader; */
            footer: mainFooter;
        }

         /* Header Styling */
        /* .header-content {
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
        } */

        /* Footer Styling */
        /* .footer-content {
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 9px;
            color: #444;
            width: 100%;
        }
        .footer-left { float: left; font-weight: bold; text-transform: uppercase; }
        .footer-right { float: right; font-weight: bold; text-transform: uppercase; } */

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 14px;
            margin-top: 18px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
        }

        .box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            padding: 2px 6px;
            font-size: 10px;
            border-radius: 4px;
        }

        .step {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #10b981;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <!-- Defined Header -->
    <!-- <htmlpageheader name="mainHeader">
        <div class="header-content">
            <h1>Treatment Plan Session</h1>
        </div>
    </htmlpageheader> -->

    <!-- Defined Footer -->
    <htmlpagefooter name="mainFooter">
        <table width="100%" style="font-size: 10pt; border: none;">
            <tr>
                <td align="left" style="border: none;">
                    <span class="muted">Confidential – For Professional Use Only</span>
                </td>
                <td align="center" style="border: none;">
                    <span class="muted">Page {PAGENO} / {nbpg}</span>
                </td>
                <td align="right" style="border: none;">
                    <span class="muted">Generated on: {{ date('m/d/Y') }}</span>
                </td>
            </tr>
        </table>
    </htmlpagefooter>
    
    @forelse ($sessions['treatments'] as $session)
        <!-- <h1>Treatment Session Protocol</h1>
        <div class="muted">Confidential – For Professional Use Only</div> -->

        <div class="box">
            <strong>{{ $session['title'] }}</strong><br>
            <span class="badge">Session {{ $session['session_number'] }}</span> |
            <span class="badge">Week {{ $session['week'] }}</span> |
            <span class="badge">{{ $session['treatment_time'] }} mins</span>
        </div>

        <h2>Preparations Checklist (Therapist)</h2>
        <ul>
        @foreach ($session['preparations_checklist_for_therapist'] as $item)
            <li>{{ $item }}</li>
        @endforeach
        </ul>

        <h2>Concerns Addressed</h2>
        <ul>
        @foreach ($session['concerns_addressed'] as $concern)
            <li>
                <strong>{{ $concern['concern'] }}</strong> :
                {{ $concern['current_value'] }} → {{ $concern['target_value'] }}
            </li>
        @endforeach
        </ul>

        <h2>Treatment Steps</h2>

        @foreach ($session['steps'] as $step)
            <div class="step">
                <strong>Step {{ $step['step_number'] }}</strong>
                <span class="badge">{{ $step['duration'] }} mins</span>

                <p class="muted">
                    <strong>Equipment / Ingredients:</strong>
                    {{ implode(', ', $step['ingredients_equipments']) }}
                </p>

                <p>{{ $step['how_to_do'] }}</p>
            </div>
        @endforeach
        @if(!$loop->last)
            <pagebreak />
        @endif
    @empty
        <div style="text-align: center; padding-top: 50px;">
            <p>No data available.</p>
        </div>
    @endforelse
</body>
</html>
