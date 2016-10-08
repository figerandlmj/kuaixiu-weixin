<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class LoginController extends BaseController
{
    public function loginAction(Request $request)
    {
        if ($this->getMySession('store_id')) {
            return $this->redirect($this->generateUrl('zm_store_store'));
        }

        $error = array('login_name' => '', 'password' => '', 'content' => '');

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {
            $login_name = trim($request->request->get('login_name'));
            $password = $request->request->get('password');

            if(preg_match('/^1([0-9]{9})/',$login_name)) {
                $store_info = $conn->fetchAssoc("SELECT * FROM store WHERE phone = ? LIMIT 1", array($login_name));
            } else {
                $store_info = $conn->fetchAssoc("SELECT * FROM store WHERE store_tel = ? LIMIT 1", array($login_name));
            }

            if (isset($store_info['status']) && $store_info['status'] == 1) {
                $salt = $store_info['salt'];
                $test_pass = sha1(sha1(md5(trim($password)).$salt));
                if(preg_match('/^1([0-9]{9})/',$login_name)) {
                    $store_test = $conn->fetchAssoc("SELECT * FROM store WHERE phone = ? AND password = ? LIMIT 1", array($login_name, $test_pass));
                } else {
                    $store_test = $conn->fetchAssoc("SELECT * FROM store WHERE store_tel = ? AND password = ? LIMIT 1", array($login_name, $test_pass));
                }

                if (!empty($store_test)) {
                    if(preg_match('/^1([0-9]{9})/', $login_name)) {
                        $date =date('Y-m-d',strtotime($store_test['sign_time']));
                        if(time()-strtotime($date) > 24 * 60 * 60 * 60) {
                            $sign['sign'] = '0';
                            $conn->update('store', $sign, array('phone' => $login_name));
                        }
                        $time = array();
                        $time['last_login_time'] = date('Y-m-d H:i:s');
                        $time['last_old_login_time'] = $store_test['last_login_time'];
                        $conn->update('store', $time, array('phone' => $login_name));
                    } else {
                        $date =date('Y-m-d',strtotime($store_test['sign_time']));
                        if(time()-strtotime($date) > 24 * 60 * 60 * 60) {
                            $sign['sign'] = '0';
                            $conn->update('store', $sign, array('store_tel' => $login_name));
                        }
                        $time = array();
                        $time['last_login_time'] = date('Y-m-d H:i:s');
                        $time['last_old_login_time'] = $store_test['last_login_time'];
                        $conn->update('store', $time, array('store_tel' => $login_name));
                    }
                    $data['store_id'] = $store_test['id'];
                    $data['login_name'] = $login_name;
                    $data['store_type'] = $store_info['type'];
                    $this->setMySession($data);
                    return $this->redirect($this->generateUrl('zm_store_index', array('status' => 1)));

                } else {
                    $error['login_name'] = $login_name;
                    $error['password'] = $password;
                    $error['content'] = '密码错误';
                }

            } elseif (isset($store_info['status']) && ($store_info['status'] == 0 || $store_info['status'] == 2)) {
                return $this->redirect($this->generateUrl('zm_store_checking', array('status' => $store_info['status'], 'type' => $store_info['type'])));
            }  else {
                $error['login_name'] = $login_name;
                $error['password'] = $password;
                $error['content'] = '商户未注册';
            }
        }

        return $this->render('ZMStoreBundle:Login:login.html.twig', array('error' => $error));
    }

    /**
     * 审核中
     *
     * @param int $status
     * @param int $type
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkingAction($status = 0, $type = 0)
    {
        $conn = $this->getDoctrine()->getConnection();

        $setting = $conn->fetchAssoc("SELECT * FROM setting");

        if ($type == 1) {
            $email = 'inividual@jdkuaixiu.com';
        } else {
            $email = 'shops@jdkuaixiu.com';
        }

        return $this->render('ZMStoreBundle:Login:checking.html.twig', array('setting' => $setting, 'status' => $status, 'email' => $email));
    }

    public function forgetAction(Request $request)
    {
        $conn = $this->getDoctrine()->getConnection();

        $error['flag'] = '';
        $error['content'] = '';

        if ($request->getMethod() == 'POST') {
            $phone = $request->request->get('phone');
            $sms_salt = $request->request->get('sms_salt');

            $password = trim($request->request->get('password'));
            $confirm_password = trim($request->request->get('confirm_password'));

            $check = $this->check_salt($phone, 4, $sms_salt);

            if ($check['flag'] == 1 && $password == $confirm_password) {
                $data['salt'] = $this->create_random(8);
                $data['password'] = sha1(sha1(md5($confirm_password) . $data['salt']));

                $conn->update('store', $data, array('phone' => $phone));

                return $this->redirect($this->generateUrl('zm_store_login'));

            } else if ($check['flag'] != 1) {
                $error['flag'] = $check['flag'];
                $error['content'] = $check['content'];
            } else if ($password != $confirm_password) {
                $error['flag'] = 6;
                $error['content'] = '两次密码不一致';
            }

        }

        return $this->render('ZMStoreBundle:Login:forget.html.twig', array('error' => $error));
    }

    /**
     * 退出登录
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function logoutAction()
    {
        $this->clearSession();
        return $this->redirect($this->generateUrl('zm_store_login'));
    }

    public function registerOneAction(Request $request)
    {
        $error['flag'] = '';
        $error['content'] = '';

        if ($request->getMethod() == 'POST') {
            $phone = $request->request->get('phone');
            $sms_salt = $request->request->get('sms_salt');
            $password = $request->request->get('password');
            $store_type = $request->request->get('store_type');
            $confirm_password = $request->request->get('confirm_password');

            $check = $this->check_salt($phone, 3, $sms_salt);

            if ($check['flag'] == 1 && $password == $confirm_password) {

                $this->setMySession(array($this->session_store_register_phone => $phone, $this->session_store_register_password => $confirm_password, $this->session_store_register_type => $store_type));
                return $this->redirect($this->generateUrl('zm_store_register_two'));
            } else if ($password != $confirm_password) {
                $error['flag'] = 6;
                $error['content'] = '两次密码不一致';
            } else {
                $error['flag'] = $check['flag'];
                $error['content'] = $check['content'];
            }
        }

        return $this->render('ZMStoreBundle:Login:registerOne.html.twig', array('error' => $error));
    }

    public function registerTwoAction(Request $request)
    {
        $data['phone'] = $this->getMySession($this->session_store_register_phone);

        if (empty($data['phone'])) {
            return $this->redirect($this->generateUrl('zm_store_register_one'));
        }

        $password = $this->getMySession($this->session_store_register_password);
        $data['salt'] = $this->create_random(8);
        $data['password'] = sha1(sha1(md5($password) . $data['salt']));
        $data['type'] = $this->getMySession($this->session_store_register_type);

        $conn = $this->getDoctrine()->getConnection();

        $error = array();

        if ($request->getMethod() == 'POST') {
            $data['store_name'] = $request->request->get('store_name');
            if ($data['type'] == 1 && empty($data['store_name'])) {
                $error['store_name'] = '技师昵称不能为空';
            } else if ($data['type'] == 2 && empty($data['store_name'])) {
                $error['store_name'] = '店铺名称不能为空';
            }
            $data['link_man'] = $request->request->get('link_man');
            if ($data['type'] == 1 && empty($data['link_man'])) {
                $error['link_man'] = '联系人不能为空';
            } else if ($data['type'] == 2 && empty($data['link_man'])) {
                $error['link_man'] = '负责人不能为空';
            }
            $data['store_tel'] = $request->request->get('store_tel');
            if (empty($data['store_tel'])) {
                $error['store_tel'] = '联系方式不能为空';
            }
            $data['id_num'] = $request->request->get('id_num');
            if (empty($data['id_num'])) {
                $error['id_num'] = '身份证号码不能为空';
            }
            $data['business_number'] = $request->request->get('business_number');
            if ($data['type'] == 2 && empty($data['business_number'])) {
                $error['business_number'] = '营业执照号不能为空';
            }
            $data['res_province_id'] = $request->request->get('res_province_id');
            $data['res_city_id'] = $request->request->get('res_city_id');
            $data['res_district_id'] = $request->request->get('res_district_id');
            if (empty($data['res_province_id']) || empty($data['res_city_id']) || empty($data['res_district_id'])) {
                $error['area'] = '区域不能为空';
            }
            $data['address'] = $request->request->get('address');
            if (empty($data['address'])) {
                $error['address'] = '地址不能为空';
            }

            if (empty($_FILES['id_card']['name'])) {
                $error['id_card'] = '请上传身份证照片';
            }

            if (empty($_FILES['qualification']['name']) && $data['type'] == 1) {
                $error['qualification'] = '请上传技师证书';
            }

            if (empty($_FILES['business_license']['name']) && $data['type'] == 2) {
                $error['business_license'] = '请上传营业执照';
            }

            $data['email'] = $request->request->get('email');
            if (!empty($data['email']) && !preg_match("/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i", $data['email'])) {
                $error['email'] = '邮箱格式不正确';
            }
            $data['competence'] = $request->request->get('competence');

            $appliances = $request->request->get('appliances');

            if (empty($appliances)) {
                $error['appliance'] = '家电分类不能为空';
            } else if (!is_array($appliances)) {
                $error['appliance'] = '家电分类数据有误';
            }

            if (empty($error)) {

                $data['register_date'] = date('Y-m-d H:i:s');


                $conn->insert('store', $data);

                $store_id = $conn->lastInsertId();

                $appliances = $request->request->get('appliances');

                if (is_array($appliances)) {
                    foreach($appliances as $key => $value) {
                        $kind['store_id'] = $store_id;
                        $kind['appliance_id'] = $value;
                        $conn->insert('store_appliance', $kind);
                    }
                }

                $path = '../../kuaixiu-website/web/image/store/';

                //店铺照片
                $qual['image'] = '';
                $qual['type'] = 0;
                $qual['store_id'] = $store_id;

                $conn->insert('store_qualification', $qual);

                if (isset($_FILES['id_card']) && !empty($_FILES['id_card']['name'])) {
                    $qual['image'] = $this->uploadPic('id_card', $path);
                    $qual['type'] = 2;
                    $qual['store_id'] = $store_id;

                    $conn->insert('store_qualification', $qual);
                } else {
                    $error['id_card'] = '请上传身份证照片';
                }

                if (isset($_FILES['qualification']) && !empty($_FILES['qualification']['name']) && $data['type'] == 1) {
                    $qual['image'] = $this->uploadPic('qualification', $path);
                    $qual['type'] = 1;
                    $qual['store_id'] = $store_id;

                    $conn->insert('store_qualification', $qual);
                } else {
                    $error['qualification'] = '请上传技师证书';
                }

                if (isset($_FILES['business_license']) && !empty($_FILES['business_license']['name']) && $data['type'] == 2) {
                    $qual['image'] = $this->uploadPic('business_license', $path);
                    $qual['type'] = 3;
                    $qual['store_id'] = $store_id;

                    $conn->insert('store_qualification', $qual);
                } else {
                    $error['business_license'] = '请上传营业执照';
                }

                $this->clearSession();

                return $this->redirect($this->generateUrl('zm_store_checking', array('status' => 0, 'type' => $data['type'])));
            }

        }

        return $this->render('ZMStoreBundle:Login:registerTwo.html.twig', array('store_type' => $data['type'], 'error' => $error, 'province_list' => $this->get_province(), 'appliance_list' => $this->get_appliance()));
    }

    public function agreementAction()
    {
        return $this->render('ZMStoreBundle:Login:agreement.html.twig');
    }
}
