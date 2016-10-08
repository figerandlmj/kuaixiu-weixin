<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class MemberController extends BaseController
{
    public function indexAction()
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();
        $data = $conn->fetchAssoc("SELECT avatar, points, phone FROM user WHERE id = ? LIMIT 1", array($member_id));

        return $this->render('ZMMemberBundle:Member:index.html.twig', array('data' => $data, 'webIp' => $this->webIp));
    }

    public function infoAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {
            $data['sex'] = $request->request->get('sex');
            $data['real_name'] = $request->request->get('real_name');
            $data['contact_way'] = $request->request->get('contact_way');
            $data['res_province_id'] = $request->request->get('res_province_id');
            $data['res_city_id'] = $request->request->get('res_city_id');
            $data['res_district_id'] = $request->request->get('res_district_id');
            $data['address'] = $request->request->get('address');

            $conn->update('user', $data, array('id' => $member_id));

            return $this->redirect($this->generateUrl('zm_member_member_info'));
        }

        $member = $conn->fetchAssoc("SELECT u.phone, u.avatar, u.sex, u.contact_way, u.real_name, u.points, u.res_province_id, u.res_city_id, u.res_district_id, rp.name AS rp_name, rc.name AS rc_name, rd.name AS rd_name, u.address FROM user u LEFT JOIN res_province rp ON rp.id = u.res_province_id LEFT JOIN res_city rc ON rc.id = u.res_city_id LEFT JOIN res_district rd ON rd.id = u.res_district_id WHERE u.id = ? LIMIT 1", array($member_id));

        return $this->render('ZMMemberBundle:Member:info.html.twig', array('data' => $member, 'webIp' => $this->webIp, 'province_list' => $this->get_province()));
    }

    public function pointsAction()
    {
        return $this->render('ZMMemberBundle:Member:points.html.twig');
    }

    public function passwordAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $error = array('flag' => '', 'content' => '');

        if ($request->getMethod() == 'POST') {
            $password_old = $request->request->get('password_old');
            $password_new = $request->request->get('password_new');
            $confirm_password_new = $request->request->get('confirm_password_new');

            if ($password_new == $confirm_password_new) {

                $conn = $this->getDoctrine()->getConnection();

                $member_password = $conn->fetchAssoc("SELECT password, salt FROM user WHERE id = ? LIMIT 1", array($member_id));

                if (sha1(sha1(md5($password_old).$member_password['salt'])) == $member_password['password']) {
                    $salt = $this->create_random(8);
                    $conn->update('user', array('password' => sha1(sha1(md5($confirm_password_new).$salt)), 'salt' => $salt, 'password_strength' => $this->password_strength($confirm_password_new)), array('id' => $member_id));

                    return $this->redirect($this->generateUrl('zm_member_member'));
                } else {
                    $error['flag'] = 2;
                    $error['content'] = '原密码错误';
                }
            } else {
                $error['flag'] = 3;
                $error['content'] = '两次输入密码不一致';
            }
        }

        return $this->render('ZMMemberBundle:Member:password.html.twig', array('error' => $error));
    }
}
