LV7. Secret Document Storage 
Tổng quan:
Ban đầu mình nhìn vào source code để suy luận làm thế nào để có thể lấy dược flag bây giờ nhìn vào source code mà bài đã cho kểm tra file dockerfile và init.sql thì thấy đượcc 

<img width="867" height="468" alt="image" src="https://github.com/user-attachments/assets/8814dc04-ccff-4b24-89ef-8078ee04bcb6" />

<img width="903" height="513" alt="image" src="https://github.com/user-attachments/assets/22f6b261-bc1f-4529-9635-eb3d8157ab7e" />

Thấy ở tron bảng secret có chứa 1 bảng bí mật đây là 1 phần của flag tiếp theo thì ở dockerfile có file /readflag bị set quyền chmod 700 (chỉ root mới đọc được)

Thấy lệnh chmod u+s /usr/bin/find. Đây là điểm mấu chốt! Lệnh find có đặc quyền SUID, tức là ai chạy nó cũng mang quyền của root.

Từ đây có thẻ xác định mục tiêu của mình là phải tìm cách thực thi được lệnh find trên server (cần RCE - Remote Code Execution) và tìm cách đọc database init.sql.
Bây giờ ta đọc qua các file php mà bài cho ở đây có thể thấy trong file dashboard.php

<img width="759" height="365" alt="image" src="https://github.com/user-attachments/assets/704380de-a0aa-48ab-aa6d-86f6ab4bdbdb" />

Có lệnh include($_POST['filename']);. Cực kỳ nguy hiểm! Đây là lỗ hổng LFI (Local File Inclusion) dẫn đến RCE.

Điều kiện: Phải vượt qua if ($_SESSION['admin'])

Từ đây ta xem tiếp sang file admin.php để tìm cách set được quyền admin 

<img width="1055" height="233" alt="image" src="https://github.com/user-attachments/assets/bcec5504-2c49-4658-8b45-475493eaff20" />

Ở đây ta có thể thấy điều kiện là phải có access_code và được truy cập từ localhost ở đây lại xuất hiện 1 vấn đề nữa là ta không ở trong cục bộ nên bây giờ ta phải ép server tự gọi chính nó  ở đây ta sẽ sử dụng kĩ thuật SSRF

Ở file delete.php có class Requests với hàm __destruct() gọi curl_exec() 

<img width="832" height="613" alt="image" src="https://github.com/user-attachments/assets/3a336a91-a28b-4f3c-8e66-d243b695a125" />

Đồng thời có lệnh file_get_contents($filePath); nhận input từ người dùng
Có dòng ini_set('phar.readonly',0);.

Suy luận: Kết hợp file_get_contents + phar.readonly=0 + class Requests có __destruct = Lỗ hổng Phar Deserialization hoàn hảo.
report.php: Tính năng upload file.

Lỗ hổng: Chỉ kiểm tra $fileType (MIME type) chứ không kiểm tra nội dung hay phần mở rộng đuôi file một cách khắt khe. Có thể upload file mã độc.

Từ những thứ đã nêu ở trên bây giờ ta tiến hành khai thác 
Các bước thực hiện 

Làm sao để có thể rce qua dashboard

Ta cần gọi include() vào một file có chứa mã PHP.
=> Giải pháp: Dùng report.php upload một bức ảnh (chứa mã PHP độc hại bên trong), sau đó lấy tên file và ném vào dashboard.php.
=> Vướng mắc: dashboard.php yêu cầu phải có quyền Admin.

Bây giờ ta cần tìm cách để có thể lên quyền admin 
Phải vượt qua file admin.php. Điều kiện là IP 127.0.0.1 và biết access_code.
=> Giải pháp: Sử dụng kĩ thuật SSRF (Server-Side Request Forgery). Ta thấy class Requests trong delete.php có dùng curl. Nếu ta điều khiển được class này, ta có thể bắt nó thực hiện 1 lệnh POST đến 127.0.0.1/admin.php kèm theo session của ta.

=> Vướng mắc 1: Làm sao kích hoạt class Requests?

=> Vướng mắc 2: Mình chưa biết access_code là gì.

Tiếp theo ta sử dụng phar để khai thác
Kích hoạt class Requests bằng Phar Deserialization

Ở delete.php, server gọi file_get_contents() với input của ta.
Khi object Requests được giải nén, nó sinh ra trên bộ nhớ. Khi chạy xong script, PHP sẽ dọn dẹp bộ nhớ và gọi hàm __destruct() (Hàm hủy).

Hàm hủy này lại chứa lệnh curl_exec(). Ta có SSRF được kích hoạt.
=> Ta sẽ gói object Requests (đã set cấu hình payload) vào một file .phar, giả danh thành .png rồi upload qua report.php, sau đó gọi delete.php?title=phar://... để kích hoạt.


Cơ chế Phar: Nếu ta đưa vào chuỗi phar://duong_dan_file, PHP sẽ phân tích cú pháp metadata của file Phar đó. Quá trình này tự động kích hoạt quá trình Deserialization (giải nén object).

Bây giờ tiếp theo ta càn tìm access_code và 1 phần của flag 
Trước khi làm SSRF lên Admin, ta phải dùng chính chiêu Phar Deserialization này nhưng đổi payload của curl.

Thư viện curl không chỉ gọi được http://, mà còn gọi được giao thức file:// để đọc file nội bộ.

Ta tạo file Phar, bắt curl đọc file:///var/www/html/admin.php để xem mã nguồn và lấy được chữ accesscode dạng plaintext.

Tiếp tục bắt curl đọc file:///docker-entrypoint-initdb.d/init.sql để lấy nửa sau của Flag.

Khi đã có quyền Admin, ta quay lại Mắt xích 1: Upload file ảnh chứa payload PHP, gọi LFI từ dashboard.php.

Vì sao phải dùng lệnh find? Vì /readflag không cho phép user www-data (user chạy web server) đọc.

Tuy nhiên, do cấu hình sai ở Dockerfile (chmod u+s /usr/bin/find), ta mượn quyền root của lệnh find để đọc file /readflag. Lệnh exec("find /readflag -exec cat {} \;") chính là nhát kiếm cuối cùng.

BÀI LÀM 

Đầu tiên như mình đã nói là chúng ta cần tạo 1 phar để có thể đọc file admin.php và init.sql của bài ta tiến hành tạo phar như ý tưởng mình đã nói ở trên tiến hành upload và xóa

<img width="1494" height="615" alt="image" src="https://github.com/user-attachments/assets/368a9af2-8da5-4195-bf65-51e8a7e451a2" />

sau khi mã hóa bằng md5 thì ta xác định đưuọc dạng plaintext của access_code là 'accesscode' tiếp theo ta cần đọc file init.sql ta chỉ cần đổi đường dẫn là được 

<img width="1544" height="537" alt="image" src="https://github.com/user-attachments/assets/d18ad35f-c6c2-4140-83bf-5708c6a5f32a" />

Ở đây ta có được thêm phần sau của flag 

Gemini đã nói
Tuyệt vời! Bạn đã thực thi thành công cả hai giai đoạn một cách hoàn hảo.

Hãy nhìn vào kết quả bạn vừa thu được:

Từ file init.sql: Ta đã lấy được nửa sau của Flag nằm trong bảng secret:
under_the_red_house_roof!}

Tiếp theo ta cần leo quyền là admin chúng ta lại tạo phar để khai thác ssrf

<img width="1493" height="410" alt="image" src="https://github.com/user-attachments/assets/0b772b9d-2a38-41af-b4fd-f594f364c310" />

Từ request SSRF: Dòng chữ Administrator privileges have been granted. xác nhận session hiện tại của ta (cookie kel94qk24rd9alu8f877m2ms0g) đã chính thức được cấp quyền Admin.

Vì ta đã là Admin, dashboard.php sẽ cho phép bạn sử dụng tham số filename để include() một file bất kỳ. Trong PHP, hàm include() cực kỳ nguy hiểm nếu không được kiểm soát, vì nó sẽ đọc nội dung file và thực thi bất kỳ mã PHP nào nó tìm thấy bên trong, bất kể phần mở rộng của file là .txt, .png hay .jpg

Đồng thời, hệ thống có một điểm yếu cấu hình (như ta thấy trong Dockerfile trước đó): lệnh find được set bit SUID (chmod u+s). Điều này có nghĩa là khi bạn chạy find, nó sẽ thực thi với quyền của người tạo ra nó (root), cho phép bạn đọc file /readflag đang bị khóa quyền truy cập.

Chúng ta cần upload webshell với nội dung chứa Magic Bytes của file ảnh và mã thực thi PHP:

<img width="1428" height="782" alt="image" src="https://github.com/user-attachments/assets/86af5dd0-e337-4f11-affc-cb9a89b2b924" />

bây giờ ta chỉ cần đọc nó qua dashboard.php

<img width="1504" height="567" alt="image" src="https://github.com/user-attachments/assets/28fa1f5b-a54d-4674-995d-1cb1cd3b8c1f" />

Ghép 2 phần lại có flag hoàn chỉnh là DH{M3rRy_ChristM4s!_th3_G1fT_1s_under_the_red_house_roof!}






