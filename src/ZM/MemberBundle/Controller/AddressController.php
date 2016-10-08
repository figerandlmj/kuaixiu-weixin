<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class AddressController extends BaseController
{
    public function indexAction()
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        $address_list = $conn->fetchAll("SELECT * FROM user_address WHERE user_id = ?", array($member_id));
        $default_address_id = $conn->fetchColumn("SELECT user_address_id FROM user WHERE id = ? LIMIT 1", array($member_id));
        if (!empty($address_list)) {
            foreach ($address_list as $key => $value) {

                if ($default_address_id == $address_list[$key]['id']) {
                    $address_list[$key]['is_default'] = 1;
                } else {
                    $address_list[$key]['is_default'] = 0;
                }
                $address_list[$key]['res_province_name'] = $conn->fetchColumn("SELECT name FROM res_province WHERE id = ? LIMIT 1", array($address_list[$key]['res_province_id']));
                $address_list[$key]['res_city_name'] = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($address_list[$key]['res_city_id']));
                $address_list[$key]['res_district_name'] = $conn->fetchColumn("SELECT name FROM res_district WHERE id = ? LIMIT 1", array($address_list[$key]['res_district_id']));
            }
        }
        return $this->render('ZMMemberBundle:Address:index.html.twig', array('data' => $address_list));
    }

    public function infoAction(Request $request, $id)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        $error = array('content' => '');

        if ($request->getMethod() == 'POST') {
            $data['name'] = $request->request->get('name');
            $data['res_province_id'] = $request->request->get('res_province_id');
            $data['res_city_id'] = $request->request->get('res_city_id');
            $data['res_district_id'] = $request->request->get('res_district_id');
            $data['address'] = $request->request->get('address');
            $data['phone'] = $request->request->get('phone');

            if ($id) {
                $conn->update('user_address', $data, array('id' => $id));
                return $this->redirect($this->generateUrl('zm_member_address'));
            } else {
                $data['user_id'] = $member_id;

                $check = $conn->fetchAssoc("SELECT * FROM user_address WHERE name = ? AND phone = ? AND res_province_id = ? AND res_city_id = ? AND res_district_id = ? AND address = ? AND user_id = ? LIMIT 1", array($data['name'], $data['phone'], $data['res_province_id'], $data['res_city_id'], $data['res_district_id'], $data['address'], $data['user_id']));

                if (empty ($check)) {
                    $conn->insert('user_address', $data);

                    return $this->redirect($this->generateUrl('zm_member_address'));
                } else {
                    $error['content'] = '地址不能重复';
                }
            }
        }

        $address = $conn->fetchAssoc("SELECT * FROM user_address WHERE id = ? LIMIT 1", array($id));

        if ($address) {
            $address['province_name'] = $conn->fetchColumn("SELECT name FROM res_province WHERE id = ? LIMIT 1", array($address['res_province_id']));
            $address['city_name'] = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($address['res_city_id']));
            $address['district_name'] = $conn->fetchColumn("SELECT name FROM res_district WHERE id = ? LIMIT 1", array($address['res_district_id']));
        }

        $province_list = $this->get_province();

        return $this->render('ZMMemberBundle:Address:info.html.twig', array('address' => $address, 'error' => $error, 'id' => $id, 'province_list' => $province_list));
    }
}
