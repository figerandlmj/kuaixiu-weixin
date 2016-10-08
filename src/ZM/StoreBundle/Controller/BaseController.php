<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Filesystem\Filesystem;

class BaseController extends Controller
{
    public $webIp = 'http://121.41.118.106/';
//    public $webIp = 'http://192.168.1.31/kuaixiu/web/';
    public $default_store_image = 'default_store_image.jpg';
    public $default_store_personal = 'default_store_personal.png';

    //Session Name
    public $session_store_register_phone = 'store_register_phone';
    public $session_store_register_password = 'store_register_password';
    public $session_store_register_type = 'store_register_type';

    public function is_logged()
    {
        $store_id = $this->getMySession('store_id');
        if (!empty($store_id)) {
            return intval($store_id);
        } else {
            return $this->redirect($this->generateUrl('zm_store_login'));
        }
    }

    public function ajax_is_logged()
    {
        $store_id = $this->getMySession('store_id');
        if (!empty($store_id)) {
            return intval($store_id);
        } else {
            $result['flag'] = 0;
            $result['content'] = '请登录';
            return new JsonResponse($result);
        }
    }

    /**
     * 设置session
     *
     * @param $data
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
     * 注册时发送短信
     *
     * @param type $phone
     * @param type $salt
     * @param type $type
     * @return type
     */
    public function storeMessage($phone, $salt, $type)
    {
        if ($type == 3 || $type == 4) {
            $content = '亲爱的用户，欢迎加入快修网，下面是您的验证码： ' . $salt . ' 请不要把验证码透露给他人';
        }

        $code = $this->sendMessage($phone, $content);

        if ($code != '0') {
            $code = $this->sendMessage($phone, $content);
        }

    }

    public function sendMessage($phone, $content, $stime='', $type = 'pt')
    {

        $sms_data = array(
            'sms_uid' => 'hskuaiwei',
//            'sms_pwd' => '611D17CD362AA0C5C8E8B75CA8B1',
            'sms_pwd' => '8F7B4B53B80E9DAD728A164902A2',
            'sms_url' => 'http://web.duanxinwang.cc/asmx/smsservice.aspx?'
        );

        $data = array (
            'name' => $sms_data['sms_uid'],					//用户账号
            'pwd' => $sms_data['sms_pwd'],		//
            'mobile' => $phone,				//号码
            'content' => $content,			//内容
            'stime' => $stime,					//定时发送
            'sign' => '家电快修网',
            'type' => $type,
            'extno' => ''
        );

        $re = $this->postSMS($sms_data['sms_url'], $data);			//POST方式提交   返回值示例：sms&stat=100&message=发送成功
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
    public function postSMS($url,$data='')
    {
        $row = parse_url($url);
        $host = $row['host'];
        $port = isset($row['port']) ? $row['port']:80;
        $file = $row['path'];
        $post = '';
        while (list($k,$v) = each($data))
        {
            $post .= rawurlencode($k)."=".rawurlencode($v)."&";	//转URL标准码
        }
        $post = substr( $post , 0 , -1 );
        $len = strlen($post);
        $fp = @fsockopen( $host ,$port, $errno, $errstr, 10);
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
            $receive = explode("\r\n\r\n",$receive);
            unset($receive[0]);
            return implode("",$receive);
        }
    }

    /**
     * 验证手机动态码
     *
     * @param type $phone
     * @param type $type 3:注册，4:找回密码
     * @param type $salt
     * @return array('flag','content')
     */
    public function check_salt($phone, $type, $salt)
    {
        $conn = $this->getDoctrine()->getConnection();
        $salt_info = $conn->fetchAssoc("SELECT * FROM sms_record WHERE phone = ? AND type = ? AND salt = ? LIMIT 1", array($phone, $type, $salt));
        $result = array();

        if ($salt_info['times'] >= 5) {
            $result['flag'] = 3;
            $result['content'] = '该动态码验证次数超过限制';
        } else if ($salt_info['is_used'] == 1) {
            $result['flag'] = 4;
            $result['content'] = '该动态码已被验证';
        } else if ($salt_info['salt'] != $salt) {
            //验证次数加1
            $conn->update('sms_record', array('times' => $salt_info['times'] + 1), array('id' => $salt_info['id']));
            $result['flag'] = 2;
            $result['content'] = '动态码不正确';
        } else if (time() - $salt_info['happen_time'] > 1800) {
            $result['flag'] = 5;
            $result['content'] = '验证超时';
        } else {
            $result['flag'] = 1;
            $result['content'] = '验证成功';
            $conn->update('sms_record', array('is_used' => 1), array('id' => $salt_info['id']));
        }

        return $result;
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

    public function main_year()
    {
        $data = array(
            '1' => '一年以内', '2' => '两年以内', '3' => '三年以内', '4' => '四年以内', '5' => '五年以内',
            '6' => '六年以内', '7' => '七年以内', '8' => '八年以内', '9' => '九年以内', '10' => '十年以内',
            '11' => '十一年以内', '12' => '十二年以内', '13' => '十三年以内', '14' => '十四年以内', '15' => '十五年以内',
            '16' => '十六年及以上',
        );

        return $data;
    }
}
