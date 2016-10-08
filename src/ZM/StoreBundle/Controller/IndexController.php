<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends BaseController
{
    /**
     * 订单列表
     *
     * @param Request $request
     * @param $status 订单状态 0.已取消,1.待处理,2.已完成,3.进行中
     * @return int|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, $status)
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

        switch ($status) {
            case 0:
                $order = " m.date_canceled DESC ";
                break;
            case 2:
                $order = " m.date_completed DESC ";
                break;
            case 3:
                $order = " m.date_confirm DESC ";
                break;
            default:
                $order = " m.date_added DESC ";
                break;
        }

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM maintenance WHERE store_id = ? AND status = ?", array($store_id, $status));
        $data = $conn->fetchAll("SELECT m.id AS maintenance_id, u.phone, m.order_sn, m.date_added, m.fix_way, m.remark, a.appliance_name, man.name AS manufacturer_name, ua.name, ua.phone, ua.address, rp.name AS province_name, rc.name AS city_name, rd.name AS district_name FROM maintenance m LEFT JOIN user u ON m.user_id = u.id LEFT JOIN appliance a ON a.id = m.appliance_id LEFT JOIN app_man am ON m.manufacturer_id = am.id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id LEFT JOIN user_address ua ON m.address_id = ua.id LEFT JOIN res_province rp ON rp.id = ua.res_province_id LEFT JOIN res_city rc ON rc.id = ua.res_city_id LEFT JOIN res_district rd ON rd.id = ua.res_district_id WHERE m.store_id = ? AND m.status = ? ORDER BY " . $order . " LIMIT $start, $limit", array($store_id, $status));

        foreach ($data as $key => $value) {
            $data[$key]['evaluation'] = $conn->fetchColumn("SELECT (evaluation_velocity + evaluation_quality + evaluation_attitude + evaluation_price)/4 AS evaluation FROM review WHERE maintenance_id = ?", array($value['maintenance_id']));
        }
        
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_store_index', array('status' => $status)) . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_store_index', array('status' => $status)) . '?page=' . $page . $url;

        $store_info = $conn->fetchAssoc("SELECT sign_time FROM store WHERE id = ? LIMIT 1", array($store_id));

        if (trim(date("Y-m-d")) != trim(date("Y-m-d",strtotime($store_info['sign_time'])))) {
            $is_sign = 0;
        } else {
            $is_sign = 1;
        }

        return $this->render('ZMStoreBundle:Index:index.html.twig', array('data' => $data, 'total' => $total, 'render' => $render, 'status' => $status, 'is_sign' => $is_sign));
    }

    /**
     * 订单详情
     *
     * @param Request $request
     * @param $id 订单id
     * @return int|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function infoAction(Request $request, $id)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $maintenance = $conn->fetchAssoc("SELECT m.id AS maintenance_id, u.phone, m.status, m.order_sn, m.date_added, m.fix_way, m.appliance_id, m.manufacturer_id, a.appliance_name, man.name AS manufacturer_name, ua.name, ua.phone, ua.address, rp.name AS province_name, rc.name AS city_name, rd.name AS district_name, m.date_maintenance, m.date_completed FROM maintenance m LEFT JOIN user u ON m.user_id = u.id LEFT JOIN appliance a ON a.id = m.appliance_id LEFT JOIN app_man am ON m.manufacturer_id = am.id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id LEFT JOIN user_address ua ON m.address_id = ua.id LEFT JOIN res_province rp ON rp.id = ua.res_province_id LEFT JOIN res_city rc ON rc.id = ua.res_city_id LEFT JOIN res_district rd ON rd.id = ua.res_district_id WHERE m.id = ? LIMIT 1", array($id));

        if ($request->getMethod() == 'POST') {
            
            $get_score = 0;

            //维修原因
            $maintenance_reasons = $request->request->get('maintenance_reasons_ids');
            if (is_array($maintenance_reasons) && !empty($maintenance_reasons)) {
                $data['maintenance_reasons_id'] = implode(',', $maintenance_reasons);
                $get_score += 2;
            }

            //其他原因
            $data['description'] = $request->request->get('description');
            if (!empty($data['description'])) {
                $get_score += 2;
            }
            

            //维修电器类型
            $data['maintenance_type_id'] = $request->request->get('maintenance_type_id');
            //年限
            $data['maintenance_year'] = $request->request->get('maintenance_year');
            //型号
            $data['manufacturer_model'] = $request->request->get('manufacturer_model');

            //当家电为空调时为内机号
            $data['product_sn'] = $request->request->get('product_sn');
            //当家电为空调时为外机号
            $data['machine_sn'] = $request->request->get('machine_sn');

            if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {

                $path = '../../kuaixiu-website/web/image/comment/';

                $file = $_FILES['image'];
                $num = count($file['name']);

                if ($num >= 8) {
                    $num = 8;
                }

                $image = array();
                $image_name = array();

                for ($i = 0; $i < $num; $i++) {
                    $image[$i]['name'] = $file['name'][$i];
                    $image[$i]['type'] = $file['type'][$i];
                    $image[$i]['tmp_name'] = $file['tmp_name'][$i];
                    $image[$i]['error'] = $file['error'][$i];
                    $image[$i]['size'] = $file['size'][$i];
                }

                if (!empty ($image[0]['name'])) {
                    foreach ($image as $key => $value) {
                        $_FILES['image'] = $value;
                        $image_name[$key] = $this->uploadPic('image', $path);
                    }
                    $data['image'] = serialize($image_name);
                }
            }

            $data['status'] = 2;
            $data['date_completed'] = date('Y-m-d H:i:s');

            $row = $conn->update('maintenance', $data, array('id' => $id));

            if ($row) {
                $conn->executeUpdate("UPDATE store SET score = score + ?, order_num = order_num + 1 WHERE id = ?", array($get_score, $store_id));

                //生成统计原因
                if($data['description']){
                    $other['manufacturer_id'] = $maintenance['manufacturer_id'];
                    $other['appliance_id'] = $maintenance['appliance_id'];
                    $other['maintenance_reasons_id'] = '0';
                    $row = $conn->fetchColumn("SELECT id FROM maintenance_statistics WHERE appliance_id = ? AND manufacturer_id = ?  AND maintenance_reasons_id = ? ", array($maintenance['appliance_id'],$maintenance['manufacturer_id'],$other['maintenance_reasons_id']));
                    if($row){
                        $conn->executeUpdate('UPDATE maintenance_statistics SET count = count+1 WHERE id = ?', array($row));
                    } else {
                        $conn->insert("maintenance_statistics", $other);
                    }
                }

                if($maintenance_reasons && is_array($maintenance_reasons)){
                    foreach($maintenance_reasons as $key => $v){
                        $we['manufacturer_id'] = $maintenance['manufacturer_id'];
                        $we['appliance_id'] = $maintenance['appliance_id'];
                        $we['maintenance_reasons_id'] = $v;
                        $row = $conn->fetchColumn("SELECT id FROM maintenance_statistics WHERE appliance_id = ? AND manufacturer_id = ?  AND maintenance_reasons_id = ?", array($maintenance['appliance_id'], $maintenance['manufacturer_id'],$v));
                        if($row){
                            $conn->executeUpdate('UPDATE maintenance_statistics SET count = count+1 WHERE id = ?', array($row));
                        } else {
                            $conn->insert("maintenance_statistics", $we);
                        }
                    }
                }
            }

            if ($row && $get_score > 0) {
                $conn->insert('store_reward', array(
                        'store_id' => $store_id,
                        'points' => $get_score,
                        'description' => '填写维修原因',
                        'date_added' => date('Y-m-d H:i:s'),
                        'maintenance_id' => $id));
            }

            return $this->redirect($this->generateUrl('zm_store_index', array('status' => 2)));
        }

//        $maintenance = $conn->fetchAssoc("SELECT m.id AS maintenance_id, u.phone, m.status, m.order_sn, m.date_added, m.fix_way, m.appliance_id, a.appliance_name, man.name AS manufacturer_name, ua.name, ua.phone, ua.address, rp.name AS province_name, rc.name AS city_name, rd.name AS district_name, m.date_maintenance, m.date_completed FROM maintenance m LEFT JOIN user u ON m.user_id = u.id LEFT JOIN appliance a ON a.id = m.appliance_id LEFT JOIN app_man am ON m.manufacturer_id = am.id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id LEFT JOIN user_address ua ON m.address_id = ua.id LEFT JOIN res_province rp ON rp.id = ua.res_province_id LEFT JOIN res_city rc ON rc.id = ua.res_city_id LEFT JOIN res_district rd ON rd.id = ua.res_district_id WHERE m.id = ? LIMIT 1", array($id));

        $record = $conn->fetchAssoc("SELECT mt.name AS maintenance_type, m.maintenance_year, m.maintenance_reasons_id, m.image, m.manufacturer_model, m.product_sn, m.machine_sn, m.date_maintenance, m.description, m.date_completed FROM maintenance m LEFT JOIN maintenance_type mt ON m.maintenance_type_id = mt.id WHERE m.id = ? LIMIT 1", array($id));
        if (!empty($record['maintenance_reasons_id'])) {
            $record['maintenance_reasons_id'] = $conn->fetchAll("SELECT * FROM maintenance_reasons WHERE id IN (" . $record['maintenance_reasons_id'] . ")");
        }

        if (!empty($record['image'])) {
            $record['image'] = unserialize($record['image']);
        }

        $review = $conn->fetchAssoc("SELECT * FROM review WHERE maintenance_id = ? LIMIT 1", array($id));
        if (!empty($review['image'])) {
            $review['image'] = unserialize($review['image']);
        }

        $score = $conn->fetchColumn("SELECT points FROM store_reward WHERE maintenance_id = ? AND description = '订单积分' LIMIT 1", array($id));

        $maintenance_type_list = $conn->fetchAll("SELECT * FROM maintenance_type WHERE appliance_id = ?", array($maintenance['appliance_id']));

        $maintenance_reasons_list = $conn->fetchAll("SELECT * FROM maintenance_reasons WHERE appliance_id = ? AND pid = 0", array($maintenance['appliance_id']));

        return $this->render('ZMStoreBundle:Index:info.html.twig', array('maintenance' => $maintenance, 'review' => $review, 'record' => $record, 'webIp' => $this->webIp, 'main_year' => $this->main_year(), 'maintenance_type_list' => $maintenance_type_list, 'maintenance_reasons_list' => $maintenance_reasons_list, 'score' => $score));

    }

    /**
     * 订单处理
     *
     * @param Request $request
     * @return int|JsonResponse
     */
    public function actionAjaxAction(Request $request)
    {
        $store_id = $this->ajax_is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }
        
        $id = $request->request->get('id');
        $type = $request->request->get('type');
        $remark = $request->request->get('remark');

        $conn = $this->getDoctrine()->getConnection();

        $datetime = date('Y-m-d H:i:s');
        $row = '';
        if ($type == 0) {
            $row = $conn->update('maintenance', array('status' => $type, 'remark' => '', 'date_canceled' => $datetime), array('id' => $id));
        } else if ($type == 3) {
            $row = $conn->update('maintenance', array('status' => $type, 'remark' => $remark, 'date_confirm' => $datetime), array('id' => $id));
        }

        if ($row && $type == 3) {
            $result['flag'] = 1;
            $result['content'] = '确认成功';
        } else if (empty($row) && $type == 3) {
            $result['flag'] = 2;
            $result['content'] = '确认失败';
        } else if ($row && $type == 0) {
            $result['flag'] = 3;
            $result['content'] = '取消成功';
        } else if (empty($row) && $type == 0) {
            $result['flag'] = 4;
            $result['content'] = '取消失败';
        } else {
            $result['flag'] = 5;
            $result['content'] = '非法参数';
        }

        return new JsonResponse($result);
    }
}
