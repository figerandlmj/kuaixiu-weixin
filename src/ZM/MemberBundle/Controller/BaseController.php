<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Filesystem\Filesystem;

class BaseController extends Controller
{
    public $webIp = 'http://121.41.118.106/';
    public $default_store_image = 'default_store_image.jpg';
    public $default_store_personal = 'default_store_personal.png';
    /**
     * 验证登录
     *
     * @return type
     */
    public function is_logged()
    {
        if (isset ($_COOKIE['member_id'])) {
            $this->getMySession(array('member_id' => $_COOKIE['member_id']));
        }
        return $this->getMySession('member_id');
    }

    /**
     * 检测登录
     *
     * @return Response
     */
    public function ajax_is_logged()
    {
        $member_id = $this->getMySession('member_id');

        if (!empty($member_id)) {

            return intval($member_id);
        } else {
            $result['code'] = 0;
            $result['message'] = '请登录';

            return new JsonResponse($result);
        }
    }

    /**
     * 设置session
     *
     * @param $data
     * @param $request
     */
    public function setMySession($data)
    {
        $session = $this->get('session');
        if (is_array($data)) {
            foreach ($data as $sessionName => $sessionValue) {
                // Set a session value
                $session->set($sessionName, $sessionValue);
            }
        }

    }

    /**
     * 获取session
     *
     * @param Request $request
     * @param $sessionName
     * @return mixed
     */
    public function getMySession($sessionName)
    {
        $session = $this->get('session');

        return $session->get($sessionName);
    }

    public function removeSession($sessionName)
    {
        $session = $this->get('session');

        $session->remove($sessionName);
    }

    public function clearSession()
    {
        $session = $this->get('session');

        $session->clear();
    }

    /**
     * 与密码绑定的随机码
     *
     * @param type $len
     *
     * @return string
     */
    public function create_random($len)
    {
        $random = '';
        $charset = 'abcdefghijklmnopqrstuvwxyz0123456789';

        $charset_len = strlen($charset) - 1;

        for ($i = 0; $i < $len; $i++) {
            $random .= $charset[rand(1, $charset_len)];
        }

        return $random;
    }

    /**
     * 密码强度
     *
     * @param $str string
     * @return int
     */
    public function password_strength($str)
    {
        $score = 0;

        if(preg_match("/[0-9]+/",$str))
        {
            $score ++;
        }
        if(preg_match("/[0-9]{3,}/",$str))
        {
            $score ++;
        }
        if(preg_match("/[a-z]+/",$str))
        {
            $score ++;
        }
        if(preg_match("/[a-z]{3,}/",$str))
        {
            $score ++;
        }
        if(preg_match("/[A-Z]+/",$str))
        {
            $score ++;
        }
        if(preg_match("/[A-Z]{3,}/",$str))
        {
            $score ++;
        }
        if(preg_match("/[_\-+=*!@#$%^&()]+/",$str))
        {
            $score += 2;
        }
        if(preg_match("/[_\-+=*!@#$%^&()]{3,}/",$str))
        {
            $score ++ ;
        }
        if(strlen($str) >= 10)
        {
            $score ++;
        }

        return $score;
    }

    /**
     * 手机动态码
     *
     * @param $len
     *
     * @return string
     */
    public function generate_phone_random($len)
    {
        $array = range(0, 9);
        $random = '';
        for ($i = 0; $i < $len; $i++) {
            $random .= $array[mt_rand(0, 9)];
        }

        return $random;
    }

    /**
     * 注册时发送短信
     *
     * @param type $phone
     * @param type $salt
     * @return type
     */
    public function memberMessage($phone, $salt)
    {
        $content = '亲爱的用户，欢迎加入快修网，家电有毛病，快修来搞定，下面是您的验证码： ' . $salt . ' 请不要把验证码透露给他人';
//        $content = '亲爱的用户，欢迎加入快修网，让我们帮您的生意越做越红火吧，下面是您的验证码： ' . $salt . ' 请不要把验证码透露给他人';
//        $content = '亲爱的用户，欢迎加入快修网，让我们帮您找到更多维修店，帮您收集更多的行业数据，给您带来最大的便利，下面是您的验证码：' . $salt . ' 请不要把验证码透露给他人';

        $code = $this->sendMessage($phone, $content);

        if ($code != '0') {
            $code = $this->sendMessage($phone, $content);
        }

    }

    public function sendMessage($phone, $content, $stime = '', $type = 'pt')
    {

        $sms_data = array(
            'sms_uid' => 'hskuaiwei',
//            'sms_pwd' => '611D17CD362AA0C5C8E8B75CA8B1',
            'sms_pwd' => '8F7B4B53B80E9DAD728A164902A2',
            'sms_url' => 'http://web.duanxinwang.cc/asmx/smsservice.aspx?'
        );

        $data = array(
            'name' => $sms_data['sms_uid'],       //用户账号
            'pwd' => $sms_data['sms_pwd'],        //
            'mobile' => $phone,                //号码
            'content' => $content,            //内容
            'stime' => $stime,                    //定时发送
            'sign' => '家电快修网',
            'type' => $type,
            'extno' => ''
        );

        $re = $this->postSMS($sms_data['sms_url'], $data);            //POST方式提交   返回值示例：sms&stat=100&message=发送成功
        $code = '';

        if (!empty($re)) {
            $arr = explode(',', $re);
            $code = reset($arr);
        }

        return $code;
    }

    /**
     * post方式发送短信 (短信接口)
     *
     * string $url 短信接口URL地址
     * array $data post提交内容
     *
     * @return string
     */
    public function postSMS($url, $data = '')
    {
        $row = parse_url($url);
        $host = $row['host'];
        $port = isset($row['port']) ? $row['port'] : 80;
        $file = $row['path'];
        $post = '';
        while (list($k, $v) = each($data)) {
            $post .= rawurlencode($k) . "=" . rawurlencode($v) . "&";    //转URL标准码
        }
        $post = substr($post, 0, -1);
        $len = strlen($post);
        $fp = @fsockopen($host, $port, $errno, $errstr, 10);
        if (!$fp) {
            return "$errstr ($errno)\n";
        } else {
            $receive = '';
            $out = "POST $file HTTP/1.1\r\n";
            $out .= "Host: $host\r\n";
            $out .= "Content-type: application/x-www-form-urlencoded\r\n";
            $out .= "Connection: Close\r\n";
            $out .= "Content-Length: $len\r\n\r\n";
            $out .= $post;
            fwrite($fp, $out);
            while (!feof($fp)) {
                $receive .= fgets($fp, 128);
            }
            fclose($fp);
            $receive = explode("\r\n\r\n", $receive);
            unset($receive[0]);
            return implode("", $receive);
        }
    }

    /**
     * 验证手机动态码
     *
     * @param type $phone 手机号码
     * @param type $type 1:注册，2:找回密码
     * @param type $salt 输入的短信动态码
     * @return array('code','message')
     */
    public function check_phone_salt($phone, $type, $salt)
    {
        $conn = $this->getDoctrine()->getConnection();
        $salt_info = $conn->fetchAssoc("SELECT * FROM sms_record WHERE phone = ? AND type = ? ORDER BY id DESC LIMIT 1", array($phone, $type));

        $result = array();

        if ($salt_info['times'] >= 5) {
            $result['code'] = 1003;
            $result['message'] = '该动态码验证次数超过限制';
        } else if ($salt_info['salt'] != $salt) {
            //验证次数加1
            $conn->update('sms_record', array('times' => $salt_info['times'] + 1), array('id' => $salt_info['id']));
            $result['code'] = 1002;
            $result['message'] = '动态码不正确';
        } else if ($salt_info['is_used'] == 1) {
            $result['code'] = 1004;
            $result['message'] = '该动态码已被验证';
        } else if (time() - $salt_info['happen_time'] > 1800) {
            $result['code'] = 1005;
            $result['message'] = '验证超时';
        } else {
            $result['code'] = 1001;
            $result['message'] = '验证成功';
            $conn->update('sms_record', array('is_used' => 1), array('id' => $salt_info['id']));
        }

        return $result;
    }

    public function get_province()
    {
        $conn = $this->getDoctrine()->getConnection();
        return $conn->fetchAll("SELECT * FROM res_province");
    }

    public function get_appliance()
    {
        $conn = $this->getDoctrine()->getConnection();
        return $conn->fetchAll("SELECT * FROM appliance");
    }

    /**
     * 上传图片
     *
     * @param $str
     * @param string $path
     * @param string $newFileName
     * @return string
     */
    public function uploadPic($str, $path = "image/member/avatar/", $newFileName = "")
    {

        $file_name = '';

        $fs = new Filesystem();

        //检查路径是否存在
        if (!$fs->exists($path)) {
            //如果不存在，创建目录
            $fs->mkdir($path, 0755);
        }

        //使用Upload库
        $storage = new \Upload\Storage\FileSystem($path);
        $file = new \Upload\File($str, $storage);

        if ($file->getName() != '') {//有新的图片上传
            if (empty($newFileName)) {
                $newFileName = uniqid();
                $file->setName($newFileName);
            } else {
                $file->setName($newFileName);
            }

            //验证文件上传
            $file->addValidations(array(
                //文件类型
                new \Upload\Validation\Mimetype(array('image/png', 'image/jpg', 'image/jpeg', 'image/gif')),
                //文件大小
                new \Upload\Validation\Size('5M')
            ));

            //上传文件
            try {
                //成功
                $file->upload();
                $file_name = $file->getNameWithExtension();
            } catch (\Exception $e) {
                //失败!
                $errors = $file->getErrors();
            }

        }
        return $file_name;
    }

    /**
     * 删除文件
     *
     * @param type $path_filename   路径和文件名
     */
    public function fileRemove($path_filename) {

        $fs = new Filesystem();

        if ($path_filename != '') {
            $fs->remove($path_filename);
        }
    }

    public function get_city_id()
    {
        $city_id = $this->getMySession('member_city_id');

        if (empty($city_id)) {
            $data = $this->cityId();
            $city_id = $data['city_id'];
            $conn = $this->getDoctrine()->getConnection();
            $city_name = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($city_id));
            $this->setMySession(array('member_city_id' => $city_id, 'member_city_name' => $city_name));
        }

        return $city_id;
    }

    /*
     * 获取ip地址
     *
     */
    public function GetIp()
    {
        $realip = '';
        $unknown = 'unknown';
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown)) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                foreach ($arr as $ip) {
                    $ip = trim($ip);
                    if ($ip != 'unknown') {
                        $realip = $ip;
                        break;
                    }
                }
            } else if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], $unknown)) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown)) {
                $realip = $_SERVER['REMOTE_ADDR'];
            } else {
                $realip = $unknown;
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), $unknown)) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } else if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), $unknown)) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else if (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), $unknown)) {
                $realip = getenv("REMOTE_ADDR");
            } else {
                $realip = $unknown;
            }
        }
        $realip = preg_match("/[\d\.]{7,15}/", $realip, $matches) ? $matches[0] : $unknown;
        return $realip;
    }


    public function GetIpLookup($ip = '')
    {
        if (empty($ip)) {
            $ip = $this->GetIp();
        }
        $res = $this->curlGet('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=' . $ip);
        if (empty($res)) {
            return false;
        }
        $jsonMatches = array();
        preg_match('#\{.+?\}#', $res, $jsonMatches);
        if (!isset($jsonMatches[0])) {
            return false;
        }
        $json = json_decode($jsonMatches[0], true);
        if (isset($json['ret']) && $json['ret'] == 1) {
            $json['ip'] = $ip;
            unset($json['ret']);
        } else {
            return false;
        }
        return $json;
    }

    public function cityId()
    {
        $ipInfos = $this->GetIpLookup();
        if ($this->getMySession('member_city_id')) {

            $data['city_id'] = $this->getMySession('member_city_id');
            return $data;
        } else {
            $ipInfos = $this->GetIpLookup();
            if (empty($ipInfos['city'])) {
                $data['city_id'] = '78';
                $this->setMySession($data);
            } else {
                $city = $ipInfos['city'];
                $conn = $this->getDoctrine()->getConnection();
                $info = $conn->fetchAssoc("SELECT id,name FROM res_city WHERE name like '%" . $city . "%' LIMIT 1");
                $data['city_id'] = $info['id'];
                $this->setMySession($data);
            }

            return $data;
        }
        return new Response();

    }

    public function curlGet($url)
    {
        $ch = curl_init();
        $header = "Accept-Charset: utf-8";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $temp = curl_exec($ch);
        return $temp;
    }

    /**
     * 纬度差
     *
     * @param type $distance 距离，单位km
     * @return type
     */
    public function latitude_diff($distance)
    {
        $R = 6371.004;

        return $distance / ((pi() * 2 * $R) / 360);
    }

    /**
     * 经度差
     *
     * @param type $latitude 当前纬度
     * @param type $distance 距离，单位km
     * @return type
     */
    public function longitude_diff($latitude, $distance)
    {
        $R = 6371.004;

        return $distance / (pi() * 2 * (cos($latitude) * $R) / 360);
    }
}
