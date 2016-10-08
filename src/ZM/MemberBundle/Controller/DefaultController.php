<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends BaseController
{
    /**
     * 获取手机动态码
     *
     * @param Request $request
     * @return Response
     */
    public function getPhoneSaltAction(Request $request)
    {
        $data['phone'] = $request->request->get('phone');
        $data['type'] = $request->request->get('type');

        $result['code'] = 0;

        $conn = $this->getDoctrine()->getConnection();
        $member_info = $conn->fetchAssoc("SELECT * FROM user WHERE phone = ? AND is_ec = 0 AND is_enabled = 1 LIMIT 1", array($data['phone']));

        if (!preg_match("/^1[3-8][0-9]{9}$/", $data['phone'])) {
            $result['code'] = 2;
            $result['message'] = '手机号码格式不正确';
            $result['salt'] = '';
        } else if ($data['type'] == 1 && $member_info) {
            $result['code'] = 3;
            $result['message'] = '手机号码已存在';
            $result['salt'] = '';
        } else if ($data['type'] == 2 && empty ($member_info)) {
            $result['code'] = 4;
            $result['message'] = '手机号码不存在';
            $result['salt'] = '';
        } else {
            $data['salt'] = $this->generate_phone_random(4);
            $this->memberMessage($data['phone'], $data['salt']);
            $data['happen_time'] = time();
            $data['times'] = 0;
            $data['is_used'] = 0;
            $data['ip'] = $request->getClientIp();
            $conn->insert('sms_record', $data);

            $result['code'] = 1;
            $result['message'] = '发送成功';
            $result['salt'] = $data['salt'];
        }

        return new JsonResponse($result);
    }

    public function getCityAjaxAction($id)
    {
        $conn = $this->getDoctrine()->getConnection();

        $data = $conn->fetchAll("SELECT * FROM res_city WHERE res_province_id = ?", array($id));

        return new JsonResponse($data);
    }

    public function getDistrictAjaxAction($id)
    {
        $conn = $this->getDoctrine()->getConnection();

        $data = $conn->fetchAll("SELECT * FROM res_district WHERE res_city_id = ?", array($id));

        return new JsonResponse($data);
    }

    public function getAddressAjaxAction()
    {
        $member_id = $this->ajax_is_logged();

        if (!is_int($member_id)) {
            return $member_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $address = $conn->fetchAll("SELECT * FROM user_address WHERE user_id = ?", array($member_id));

        return new JsonResponse($address);
    }

    public function getManufacturerAjaxAction(Request $request)
    {
        $conn = $this->getDoctrine()->getConnection();

        $appliance_id = $request->request->get('appliance_id');

        $manufacturer = $conn->fetchAll("SELECT am.id, man.name FROM app_man am LEFT JOIN manufacturer man ON am.manufacturer_id = man.id WHERE am.appliance_id = ?", array($appliance_id));

        return new JsonResponse($manufacturer);
    }

    public function setLocationAjaxAction(Request $request)
    {
        $conn = $this->getDoctrine()->getConnection();

        $location_json = $request->request->get('location_json');

        $province_name = $location_json[0]['address_components'][4]['long_name'];

        $city_name = $location_json[0]['address_components'][3]['long_name'];
        $district_name = $location_json[0]['address_components'][2]['long_name'];

        $location['member_province_id'] = $conn->fetchColumn("SELECT id FROM res_province WHERE name LIKE '%" . $province_name . "%'");

        $city = $conn->fetchAssoc("SELECT id, name FROM res_city WHERE res_province_id = ? AND name LIKE '%" . $city_name . "%'", array($location['member_province_id']));

        $district = $conn->fetchAssoc("SELECT id, name FROM res_district WHERE res_city_id = ? AND name LIKE '%" . $district_name . "%'", array($city['id']));

        $location['member_city_id'] = $city['id'];
        $location['member_city_name'] = $city['name'];
        $location['member_district_id'] = $district['id'];
        $location['member_district_name'] = $district['name'];

        if (empty($location['member_city_id'])) {
            $location['member_city_id'] = 88;
        }
        $location['member_latitude'] = $request->request->get('latitude');
        $location['member_longitude'] = $request->request->get('longitude');

//        if (empty($this->getMySession('member_city_id'))) {
//            $this->setMySession(array('member_city_id' => $city['id']));
//            $this->setMySession(array('member_city_name' => $city['name']));
//            $this->setMySession(array('member_district_id' => $district['id']));
//            $this->setMySession(array('member_district_name' => $district['name']));
//        }
//        $this->setMySession(array('member_latitude' => $location['member_latitude']));
//        $this->setMySession(array('member_longitude' => $location['member_longitude']));

        $this->setMySession($location);

        if (!empty ($location_json)) {
            $result['flag'] = 1;
            $result['content'] = '定位成功';
        } else {
            $result['flag'] = 2;
            $result['content'] = '定位失败';
        }

        return new JsonResponse($result);
    }
}
