<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class StoreController extends BaseController
{
    public function indexAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $store = $conn->fetchAssoc("SELECT s.id, s.store_name, s.phone, s.store_tel, s.grade, s.score, sg.level  FROM store s LEFT JOIN store_group sg ON sg.id = s.store_group_id WHERE s.id = ? LIMIT 1", array($store_id));
        $store['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE store_id = ? AND type = 0 LIMIT 1", array($store_id));
        if (empty($store['image'])) {
            $store['image'] = $this->default_store_image;
        }

        $evaluation = $conn->fetchAssoc("SELECT AVG(evaluation_attitude) AS attitude, AVG(evaluation_price) AS price, AVG(evaluation_quality) AS quality, AVG(evaluation_velocity) AS velocity FROM review WHERE (evaluation_attitude > 0 OR evaluation_price > 0 OR evaluation_quality > 0 OR evaluation_velocity > 0) AND store_id = ?", array($store_id));

        return $this->render('ZMStoreBundle:Store:index.html.twig', array('webIp' => $this->webIp, 'store' => $store, 'evaluation' => $evaluation));
    }

    /**
     * 店铺详情
     *
     * @return int|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function infoAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $store = $conn->fetchAssoc("SELECT s.*, rp.name AS rp_name, rc.name AS rc_name, rd.name AS rd_name FROM store s LEFT JOIN res_province rp ON rp.id = s.res_province_id LEFT JOIN res_city rc ON rc.id = s.res_city_id LEFT JOIN res_district rd ON rd.id = s.res_district_id WHERE s.id = ? LIMIT 1", array($store_id));
        $store['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE store_id = ? AND type = 0 LIMIT 1", array($store_id));
        if (empty($store['image'])) {
            $store['image'] = $this->default_store_image;
        }

        return $this->render('ZMStoreBundle:Store:info.html.twig', array('store' => $store, 'webIp' => $this->webIp));
    }

    /**
     * 更新店铺信息
     *
     * @param Request $request
     * @param $param
     * @return int|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function updateAction(Request $request, $param)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $error['content'] = '';
        $conn = $this->getDoctrine()->getConnection();

        $store = $conn->fetchAssoc("SELECT s.*, rp.name AS rp_name, rc.name AS rc_name, rd.name AS rd_name FROM store s LEFT JOIN res_province rp ON rp.id = s.res_province_id LEFT JOIN res_city rc ON rc.id = s.res_city_id LEFT JOIN res_district rd ON rd.id = s.res_district_id WHERE s.id = ? LIMIT 1", array($store_id));
        $store['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE store_id = ? AND type = 0 LIMIT 1", array($store_id));
        if (empty($store['image'])) {
            $store['image'] = $this->default_store_image;
        }

        //特约维修品牌
        $special = $conn->fetchAll("SELECT s.id, a.appliance_name, man.name AS manufacturer_name FROM sign s LEFT JOIN appliance a ON s.appliance_id = a.id LEFT JOIN app_man am ON am.id = s.manufacturer_id "
            . "LEFT JOIN manufacturer man ON am.manufacturer_id = man.id WHERE s.store_id = ?" ,array($store_id));

        $store_appliance = $conn->fetchAll("SELECT * FROM store_appliance WHERE store_id = ?", array($store_id));

        if ($request->getMethod() == 'POST') {

            if ($request->request->get('store_tel')) {
                $data['store_tel'] = $request->request->get('store_tel');
                $store_info = $conn->fetchAssoc("SELECT * FROM store WHERE id = ? LIMIT 1", array($store_id));
                if ($store_info['store_tel'] == $data['store_tel'] && $store_info['id'] != $store_id) {
                    $error['content'] = '更新失败，该固话已存在！';

                    return $this->render('ZMStoreBundle:Store:update.html.twig', array('store' => $store, 'param' => $param, 'special' => $special, 'province_list' => $this->get_province(), 'appliance_list' => $this->get_appliance(), 'store_appliance' => $store_appliance, 'webIp' => $this->webIp, 'error' => $error));
                }
            }

            if ($request->request->get('store_name')) {
                $data['store_name'] = $request->request->get('store_name');
            }
            if ($request->request->get('link_man')) {
                $data['link_man'] = $request->request->get('link_man');
            }
            if ($request->request->get('address')) {
                $data['address'] = $request->request->get('address');
            }
            if ($request->request->get('res_province_id')) {
                $data['res_province_id'] = $request->request->get('res_province_id');
            }
            if ($request->request->get('res_city_id')) {
                $data['res_city_id'] = $request->request->get('res_city_id');
            }
            if ($request->request->get('res_district_id')) {
                $data['res_district_id'] = $request->request->get('res_district_id');
            }
            if ($request->request->get('start_week')) {
                $data['start_week'] = $request->request->get('start_week');
            }
            if ($request->request->get('end_week')) {
                $data['end_week'] = $request->request->get('end_week');
            }
            if ($request->request->get('start_time')) {
                $data['start_time'] = $request->request->get('start_time');
            }
            if ($request->request->get('end_time')) {
                $data['end_time'] = $request->request->get('end_time');
            }
            if ($request->request->has('qq')) {
                $data['qq'] = $request->request->get('qq');
            }
            if ($request->request->has('weixin')) {
                $data['weixin'] = $request->request->get('weixin');
            }
            if ($request->request->has('weibo')) {
                $data['weibo'] = $request->request->get('weibo');
            }

            if ($request->request->get('appliances')) {
                $appliances = $request->request->get('appliances');
                if (is_array($appliances)) {
                    $conn->delete('store_appliance', array('store_id' => $store_id));
                    foreach ($appliances as $key => $val) {
                        $kind['appliance_id'] = $val;
                        $kind['store_id'] = $store_id;
                        $conn->insert('store_appliance',$kind);
                    }
                }
            }

            if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
                $path = '../../kuaixiu-website/web/image/store/';
//                $path = '../../kuaixiu/web/image/store/';
                $qual['image'] = $this->uploadPic('image', $path);

                $conn->update('store_qualification', $qual, array('store_id' => $store_id, 'type' => 0));
            }

            if (isset($data) && !empty($data)) {
                $conn->update('store', $data, array('id' => $store_id));
            }
            
            return $this->redirect($this->generateUrl('zm_store_store_info'));

        }

        return $this->render('ZMStoreBundle:Store:update.html.twig', array('store' => $store, 'param' => $param, 'special' => $special, 'province_list' => $this->get_province(), 'appliance_list' => $this->get_appliance(), 'store_appliance' => $store_appliance, 'webIp' => $this->webIp, 'error' => $error));
    }



    public function levelAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $data = $conn->fetchAssoc("SELECT s.id, sg.level FROM store s LEFT JOIN store_group sg ON s.store_group_id = sg.id WHERE s.id = ? LIMIT 1", array($store_id));

        return $this->render('ZMStoreBundle:Store:level.html.twig', array('data' => $data));
    }

    public function scoreAction(Request $request, $type)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $url = '';

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $conn = $this->getDoctrine()->getConnection();

        $limit = 6;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM store_reward WHERE store_id = ?", array($store_id));
        $data = $conn->fetchAll("SELECT * FROM store_reward WHERE store_id = ? ORDER BY id DESC LIMIT $start, $limit", array($store_id));

        $total_score = $conn->fetchColumn("SELECT SUM(points) FROM store_reward WHERE store_id = ?", array($store_id));

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_store_store_score', array('type' => $type)) . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_store_store_score', array('type' => $type)) . '?page=' . $page . $url;

        return $this->render('ZMStoreBundle:Store:score.html.twig', array('data' => $data, 'render' => $render, 'total_score' => $total_score));
    }

    public function howGetScoreAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        return $this->render('ZMStoreBundle:Store:howGetScore.html.twig');
    }

    public function aboutusAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        return $this->render('ZMStoreBundle:Store:aboutus.html.twig');
    }

    public function helpAction()
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        return $this->render('ZMStoreBundle:Store:help.html.twig');
    }

    public function feedBackAction(Request $request)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {
            $data['contact_way'] = $request->request->get('contact_way');
            $data['content'] = $request->request->get('content');
            $data['created_at'] = date('Y-m-d H:i:s');

            $conn->insert('store_feedback', $data);

            return new JsonResponse(array('flag' => 1, 'content' => '提交成功'));

//            return $this->redirect($this->generateUrl('zm_store_store_help'));
        }

        return $this->render('ZMStoreBundle:Store:feedBack.html.twig');
    }

    /**
     * 签到
     *
     * @return int|JsonResponse
     */
    public function signAction()
    {
        $store_id = $this->ajax_is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();
        $store_info = $conn->fetchAssoc(" SELECT sign, score, sign_time FROM store WHERE id = ? LIMIT 1", array($store_id));
        $result['flag'] = 0;
        if (trim(date("Y-m-d")) != trim(date("Y-m-d",strtotime($store_info['sign_time'])))) {

            $sign_time = date('Y-m-d H:i:s');
            $balance = '0.5';
            $conn->executeUpdate("UPDATE store SET score = score + ?, sign = sign + 1, sign_time = ?  WHERE id = ?", array((float)$balance, $sign_time , $store_id));
            $reward = array(
                'store_id' => $store_id,
                'points' => '0.5',
                'description' => '每日签到',
                'date_added' => date('Y-m-d H:i:s')
            );
            $conn->insert('store_reward',$reward);
            $result['flag'] = 1;
            $result['content'] = '签到成功';

        } else {
            $result['flag'] = 2;
            $result['content'] = '亲，不能重复签到哦~';
        }

        return new JsonResponse($result);
    }
}
