<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class LoginController extends BaseController
{
    public function loginAction(Request $request)
    {
        $error = array('phone' => '', 'password' => '', 'content' => '');

        if ($this->getMySession('member_id')) {
            return $this->redirect($this->generateUrl('zm_member_index'));
        }
        $conn = $this->getDoctrine()->getConnection();

        $from_url = $request->query->get('from_url');

        if ($request->getMethod() == 'POST') {

//            $remember = $request->request->get('remember');

            $from_url = $request->request->get('from_url');

            $phone = $request->request->get('phone');
            $password = $request->request->get('password');

            $member_salt = $conn->fetchColumn("SELECT salt FROM user WHERE phone = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($phone));
            $encrypt_password = sha1(sha1(md5($password) . $member_salt));
            $member_info = $conn->fetchAssoc("SELECT * FROM user WHERE phone = ? AND password = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($phone, $encrypt_password));
            $is_sign = 0;
            if (substr($member_info['sign_time'], 0, 10) == date('Y-m-d')) {
                $is_sign = 1;
            }
            if ($member_info) {

                $data = array(
                    'member_id' => $member_info['id'],
                    'member_phone' => $member_info['phone'],
                    'member_name' => $member_info['name'],
                    'member_avatar' => $member_info['avatar'],
                    'member_is_sign' => $is_sign
                );
//                if ($remember == 1) {
//                    $time = time() + 3600 * 24 * 7;
//                    setcookie('member[member_id]', $member_info['id'], $time, '/');
//                    setcookie('member[member_phone]', $member_info['phone'], $time, '/');
//                    setcookie('member[member_name]', $member_info['name'], $time, '/');
//                    setcookie('member[member_avatar]', $member_info['avatar'], $time, '/');
//                }
                $this->setMySession($data);
                $conn->update('user', array('user_login_time' => date('Y-m-d H:i:s'), 'user_old_login_time' => $member_info['user_login_time'], 'password_strength' => $this->password_strength($password)), array('id' => $member_info['id']));
                if (!empty($from_url)) {
                    return $this->redirect(urldecode($from_url));
                }
                return $this->redirect($this->generateUrl('zm_member_index'));
            } else {
                $error['phone'] = $phone;
                $error['password'] = $password;
                $error['content'] = '用户名或密码错误';
            }
        }

        return $this->render('ZMMemberBundle:Login:login.html.twig', array('error' => $error, 'from_url' => $from_url));
    }

    public function loginSaltAction(Request $request)
    {
        if ($this->getMySession('member_id')) {
            return $this->redirect($this->generateUrl('zm_member_index'));
        }

        $conn = $this->get('database_connection');

        $from_url = $request->query->get('from_url');

        $error = array('phone' => '', 'salt' => '', 'content' => '');

        if ($request->getMethod() == 'POST') {

            $error['phone'] = $request->request->get('phone');
            $error['salt'] = $request->request->get('salt');
            $user = $conn->fetchAssoc("SELECT * FROM user WHERE phone = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($error['phone']));

            $sms_check = $this->check_phone_salt($error['phone'], 7, $error['salt']);

            if ($sms_check['code'] != 1001) {
                $error['content'] = $sms_check['message'];
            } else {

                if (empty($user)) {
                    $conn->insert('user', array('phone' => $error['phone'], 'name' => $error['phone'], 'user_old_login_time' => date('Y-m-d H:i:s'), 'user_login_time' => date('Y-m-d H:i:s')));
                    $user_id = $conn->lastInsertId();
                    $data = array(
                        'member_id' => $user_id,
                        'member_phone' => $error['phone'],
                        'member_name' => $error['phone'],
                        'member_avatar' => '',
                        'member_is_sign' => 0
                    );
                } else {

                    $is_sign = 0;
                    if (substr($user['sign_time'], 0, 10) == date('Y-m-d')) {
                        $is_sign = 1;
                    }
                    $data = array(
                        'member_id' => $user['id'],
                        'member_phone' => $user['phone'],
                        'member_name' => $user['name'],
                        'member_avatar' => $user['avatar'],
                        'member_is_sign' => $is_sign
                    );

                    $conn->update('user', array('user_login_time' => date('Y-m-d H:i:s'), 'user_old_login_time' => $user['user_login_time'], 'password_strength' => $this->password_strength($user['password'])), array('id' => $user['id']));
                }

                $this->setMySession($data);

                if (!empty($from_url)) {
                    return $this->redirect(urldecode($from_url));
                }

                return $this->redirect($this->generateUrl('zm_member_index'));
            }
        }
        
        return $this->render('@ZMMember/Login/login_salt.html.twig', array('error' => $error, 'from_url' => $from_url));

    }

    public function registerOneAction(Request $request)
    {
        $error = array('flag' => '', 'content' => '');

        $from_url = $request->query->get('from_url');

        if ($request->getMethod() == 'POST') {
            $conn = $this->getDoctrine()->getConnection();

            $phone = $request->request->get('phone');
            $smsSalt = $request->request->get('sms_salt');

            $check = $this->check_phone_salt($phone, 1, $smsSalt);
            $user = $conn->fetchAssoc("SELECT * FROM user WHERE phone = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($phone));

            if (!empty ($user)) {
                $error['content'] = '手机号码已存在';

            } else if ($check['code'] == 1001 && empty ($user)) {

                $this->setMySession(array('wx_member_register_phone' => $phone));

                return $this->redirect($this->generateUrl('zm_member_register_two') . "?from_url=" . urldecode($from_url));
            } else {
                $error['flag'] = $check['code'];
                $error['content'] = $check['message'];
            }
        }
        return $this->render('ZMMemberBundle:Login:registerOne.html.twig', array('error' => $error, 'from_url' => $from_url));
    }

    public function registerTwoAction(Request $request)
    {
        $error = array('content' => '');

        $data['phone'] = $this->getMySession('wx_member_register_phone');
        if (!isset($data['phone']) || empty($data['phone'])) {
            return $this->redirect($this->generateUrl('zm_member_register_one'));
        }

        $password = $request->request->get('password');
        $confirm_password = $request->request->get('confirm_password');

        $from_url = $request->query->get('from_url');

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST' && $password == $confirm_password) {
            $data['salt'] = $this->create_random(8); //和商家密码绑定的随机码
            $data['password'] = sha1(sha1(md5($confirm_password).$data['salt']));

            $data['user_login_time'] = date('Y-m-d H:i:s');
            $data['is_ec'] = 0;

            $conn->insert('user', $data);
            $member_id = $conn->lastInsertId();

            $this->removeSession('wx_member_register_phone');
            $this->setMySession(array('member_id' => $member_id));
            if (!empty($from_url)) {
                return $this->redirect(urldecode($from_url));
            }

            return $this->redirect($this->generateUrl('zm_member_index'));

        } else if ($password != $confirm_password) {
            $error['content'] = '两次密码不一致';
        }

        return $this->render('ZMMemberBundle:Login:registerTwo.html.twig', array('error' => $error, 'from_url' => $from_url));
    }

    public function forgetOneAction(Request $request)
    {
        $error = array('flag' => '', 'content' => '');

        if ($request->getMethod() == 'POST') {
            $conn = $this->getDoctrine()->getConnection();

            $phone = $request->request->get('phone');
            $smsSalt = $request->request->get('sms_salt');

            $check = $this->check_phone_salt($phone, 2, $smsSalt);
            $user = $conn->fetchAssoc("SELECT * FROM user WHERE phone = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($phone));

            if (empty ($user)) {
                $error['content'] = '手机号码不存在';
            } else if ($check['code'] == 1001 && $user) {
                $this->setMySession(array('wx_member_forget_phone' => $phone));
                return $this->redirect($this->generateUrl('zm_member_forget_two'));
            } else {
                $error['flag'] = $check['code'];
                $error['content'] = $check['message'];
            }
        }

        return $this->render('ZMMemberBundle:Login:forgetOne.html.twig');
    }

    public function forgetTwoAction(Request $request)
    {
        $phone = $this->getMySession('wx_member_forget_phone');

        $error = array('content' => '');

        $password = $request->request->get('password');
        $confirm_password = $request->request->get('confirm_password');

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST' && $password == $confirm_password) {
            $data['salt'] = $this->create_random(8); //和商家密码绑定的随机码
            $data['password'] = sha1(sha1(md5($confirm_password).$data['salt']));
            $data['phone'] = $phone;

            $conn->update('user', $data, array('phone' => $phone, 'is_ec' => 0, 'is_enabled' => 1));

            return $this->redirect($this->generateUrl('zm_member_login'));
        } else if ($password != $confirm_password) {
            $error['content'] = '两次密码不一致';
        }

        return $this->render('ZMMemberBundle:Login:forgetTwo.html.twig', array('phone' => $phone, 'error' => $error));
    }

    public function logoutAction()
    {
        $this->clearSession();

        return $this->redirect($this->generateUrl('zm_member_login'));
    }
}
