<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: 'Times New Roman', Times, serif;
        font-size: 13px;
        color: #111;
        line-height: 1.5;
    }
    .page {
        width: 100%;
        padding: 30px 40px;
        position: relative;
        min-height: 700px;
    }
    .header {
        text-align: center;
        border-bottom: 3px double #111;
        padding-bottom: 10px;
        margin-bottom: 18px;
    }
    .header .republic { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
    .header .brgy { font-size: 16px; font-weight: bold; margin-top: 2px; }
    .header .city { font-size: 13px; margin-top: 2px; }
    .header .office { font-size: 12px; font-style: italic; margin-top: 2px; }
    .title {
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        text-decoration: underline;
        margin: 16px 0 4px;
    }
    .document-logo {
        display: block;
        width: 62px;
        height: 62px;
        margin: 0 auto 6px;
        object-fit: contain;
    }
    .docno { text-align: center; font-size: 12px; margin-bottom: 16px; }
    table.fields {
        width: 100%;
        border-collapse: collapse;
        margin: 14px 0;
    }
    table.fields td {
        padding: 6px 8px;
        border: 1px solid #444;
        vertical-align: top;
    }
    table.fields td.label {
        width: 30%;
        background: #f2f2f2;
        font-weight: bold;
    }
    .note {
        text-align: justify;
        margin: 14px 0;
        font-size: 12.5px;
    }
    .qr-section {
        text-align: center;
        margin: 20px 0;
    }
    .qr-section img { width: 130px; height: 130px; }
    .qr-section .code { font-family: 'Courier New', monospace; font-size: 11px; margin-top: 4px; }
    .qr-caption { font-size: 11px; font-weight: bold; margin-top: 5px; }
    .verify-url { font-size: 11px; color: #333; }
    .signatories {
        margin-top: 48px;
        width: 100%;
    }
    .signatories td {
        width: 50%;
        text-align: center;
        vertical-align: top;
    }
    .signatories .line {
        display: inline-block;
        width: 220px;
        border-bottom: 1px solid #111;
        margin-top: 42px;
    }
    .signatories .name { font-weight: bold; margin-top: 4px; }
    .signatories .role { font-size: 12px; }
    .footer {
        position: absolute;
        bottom: 24px;
        left: 40px;
        right: 40px;
        border-top: 1px solid #999;
        padding-top: 6px;
        font-size: 9.5px;
        color: #555;
        text-align: center;
    }
    .status-box {
        display: inline-block;
        border: 1px solid #111;
        padding: 2px 12px;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 12px;
    }
    table.items {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
        font-size: 11.5px;
    }
    table.items th, table.items td {
        border: 1px solid #444;
        padding: 5px 6px;
        text-align: left;
    }
    table.items th { background: #f2f2f2; }
</style>
