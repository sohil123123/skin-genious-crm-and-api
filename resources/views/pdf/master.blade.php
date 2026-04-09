<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #333;
            margin: 0;
            padding: 0;
        }

        @page {
            header: html_myheader;
            footer: html_myfooter;
            margin-top: 35mm;
            margin-bottom: 0mm;
            margin-left: 15mm;
            margin-right: 15mm;
        }

        .footer-container {
            position: relative;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .footer-image {
            width: 100%;
            margin: 0;
            padding: 0;
            display: block;
        }

        .footer-content {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 20px;
        }

        .footer-text-gold {
            color: #c4b59d;
            font-size: 8px;
            margin-bottom: 5px;
        }

        .footer-text-white {
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .footer-text-small {
            color: #ffffff;
            font-size: 8px;
        }

        .page-number {
            position: absolute;
            bottom: 5px;
            right: 10px;
            color: #ffffff;
            font-size: 7pt;
        }
    </style>
</head>

<body>

<!-- ================= HEADER ================= -->
<htmlpageheader name="myheader">
    <table width="100%">
        <tr>
            <td width="15%">
                <img src="{{ public_path('images/AI-aesthetics-logo.png') }}" style="max-height: 120px;">
            </td>
            <td width="85%">
                <div style="font-size: 28pt; color: #C29F5D;"><b>A.I. AESTHETICS</b></div>
                <div style="font-size: 11pt;">BY DR. AAKRITI MEHRA</div>
                <div style="border-bottom: 2px solid #e1ceb0; margin-top: 5px;"></div>
            </td>
        </tr>
    </table>
</htmlpageheader>

@yield('content')

<!-- ================= FOOTER ================= -->
<htmlpagefooter name="myfooter">
    <!-- LAYER 1: Full width background image anchored to bottom left of page -->
    <div style="position: absolute; bottom: 0; left: -15mm; right: -15mm; z-index: -1;">
        <img src="{{ public_path('images/footer-shape-2.png') }}" style="display: block;" />
    </div>

    <!-- LAYER 2: Text explicitly separated as a block so mPDF doesn't push it below the image -->
    <div style="position: absolute; bottom: 20mm; left: -15mm; right: -15mm; text-align: center; z-index: 10;">
        <div style="color: #c4b59d; font-size: 15px; padding-bottom: 10px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            Generated via AI Aesthetics Skin Diagnostic System
        </div>
        <div style="color: #ffffff; font-size: 18px; font-weight: bold; padding-bottom: 10px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            ADVANCED FACIALS &nbsp;|&nbsp; IV WELLNESS &nbsp;|&nbsp; LASER
        </div>
        <div style="color: #ffffff; font-size: 14px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            Doctor-designed, AI Controlled and Human Delivered
        </div>
    </div>

    <!-- LAYER 3: Page number -->
    <div style="position: absolute; bottom: 10px; right: 10; color: #ffffff; font-size: 10pt; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
        Page {PAGENO} of {nbpg}
    </div>
</htmlpagefooter>

</body>
</html>
