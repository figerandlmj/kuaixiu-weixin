<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

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
        $store_info = $conn->fetchAssoc("SELECT * FROM store WHERE phone = ? LIMIT 1", array($data['phone']));

        if (!preg_match("/^1[3-8][0-9]{9}$/", $data['phone'])) {
            $result['code'] = 2;
            $result['message'] = '手机号码格式不正确';
            $result['salt'] = '';
        } else if ($data['type'] == 3 && $store_info) {
            $result['code'] = 3;
            $result['message'] = '手机号码已存在';
            $result['salt'] = '';
        } else if ($data['type'] == 4 && empty ($store_info)) {
            $result['code'] = 4;
            $result['message'] = '手机号码不存在';
            $result['salt'] = '';
        } else {
            $data['salt'] = $this->generate_phone_random(4);
            $this->storeMessage($data['phone'], $data['salt'], $data['type']);
            $data['happen_time'] = time();
            $data['times'] = 0;
            $data['is_used'] = 0;
            $data['ip'] = $request->getClientIp();
            $conn->insert('sms_record', $data);

            $result['code'] = 1;
            $result['message'] = '发送成功';
        }

        return new JsonResponse($result);
    }

    /**
     * 获取家电维修类型
     *
     * @param $id 家电id
     * @return JsonResponse
     */
    public function getMaintenanceTypeAction($id)
    {
        $conn = $this->getDoctrine()->getConnection();

        $data = $conn->fetchAll("SELECT * FROM maintenance_type WHERE appliance_id = ?", array($id));

        return new JsonResponse($data);
    }

    public function getMaintenanceReasonsAction($id)
    {
        $conn = $this->getDoctrine()->getConnection();

        $data = $conn->fetchAll("SELECT * FROM maintenance_reasons WHERE pid = ?", array($id));

        return new JsonResponse($data);
    }
}
