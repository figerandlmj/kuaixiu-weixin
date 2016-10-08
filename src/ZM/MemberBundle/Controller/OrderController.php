<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    /**
     * 订单列表
     *
     * @param Request $request
     * @param $status 0.已取消,1.待处理,2.已完成,3.进行中
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, $status)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        $url = '';

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $limit = 6;

        $start = ($page - 1) * $limit;

        $order = 'm.date_added DESC';
        if ($status == 2) {
            $order = " m.date_completed DESC ";
        } else if ($status == 3) {
            $order = " m.date_confirm DESC ";
        }

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM maintenance WHERE user_id = ? AND status = ?", array($member_id, $status));
        $data = $conn->fetchAll("SELECT m.id, s.id AS store_id, s.store_name, s.phone, s.store_tel, man.name AS manufacturer_name, a.appliance_name, m.order_sn, m.status FROM maintenance m LEFT JOIN store s ON"
            . " m.store_id = s.id LEFT JOIN app_man am ON am.id = m.manufacturer_id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id LEFT JOIN appliance a ON m.appliance_id = a.id "
            . " WHERE m.user_id = ? AND m.status = ? ORDER BY " . $order . " LIMIT $start, $limit", array($member_id, $status));
        foreach ($data as $key => $value) {
            $data[$key]['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE type = 0 AND store_id = ? LIMIT 1", array($value['store_id']));
            if (empty($data[$key]['image'])) {
                $data[$key]['image'] = $this->default_store_image;
            }
            $favorites = $conn->fetchAssoc("SELECT * FROM favorites WHERE user_id = ? AND store_id = ? LIMIT 1", array($member_id, $value['store_id']));
            if ($favorites) {
                $data[$key]['is_favorites'] = 1;
            } else {
                $data[$key]['is_favorites'] = 0;
            }
            $review = $conn->fetchAssoc("SELECT * FROM review WHERE maintenance_id = ? LIMIT 1", array($value['id']));
            if (!empty($review)) {
                $data[$key]['is_review'] = 1;
            } else {
                $data[$key]['is_review'] = 0;
            }
        }

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_order', array('status' => $status)) . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_order', array('status' => $status)) . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Order:index.html.twig', array('data' => $data , 'render' => $render, 'status' => $status, 'webIp' => $this->webIp));
    }

    public function actionAction(Request $request, $store_id)
    {
        $member_id = $this->getMySession('member_id');
//        $member_id = 2;

        if ($request->query->get('from_url')) {
            $form_url = urlencode($request->query->get('from_url'));
        }

        if (empty($member_id)) {
            if (isset($form_url) && !empty($form_url)) {
                return $this->redirect($this->generateUrl('zm_member_login') . '?from_url=' . $form_url);
            }
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {

            $data['user_id'] = $member_id;
            $data['fix_way'] = $request->request->get('fix_way');
            $data['appliance_id'] = $request->request->get('appliance_id');
            $data['manufacturer_id'] = $request->request->get('manufacturer_id');
            $data['store_id'] = $store_id;
            $data['date_added'] = date('Y-m-d H:i:s');
            $orderSN = new OrderSN();
            $data['order_sn'] = $orderSN->make_order($member_id);
            $data['is_review'] = 0;
            $data['status'] = 1;

            $address['res_province_id'] = $request->request->get('res_province_id');
            $address['res_city_id'] = $request->request->get('res_city_id');
            $address['res_district_id'] = $request->request->get('res_district_id');
            $address['address'] = $request->request->get('address');
            $address['phone'] = $request->request->get('phone');
            $address['name'] = $request->request->get('member_name');
            $address['user_id'] = $member_id;

            $check_address = $conn->fetchColumn("SELECT id FROM user_address WHERE res_province_id = ? AND res_city_id = ? AND res_district_id = ? AND address = ? AND phone = ? AND name = ? AND user_id = ? LIMIT 1",
                array($address['res_province_id'], $address['res_city_id'], $address['res_district_id'], $address['address'], $address['phone'], $address['name'], $member_id));
            if (!empty($check_address)) {
                $data['address_id'] = $check_address;
            } else {
                $conn->insert('user_address', $address);
                $data['address_id'] = $conn->lastInsertId();
            }

            $row = $conn->insert('maintenance', $data);

//            return $this->redirect($this->generateUrl('zm_member_order', array('status' => 1)));
            if ($row) {
                $result['code'] = 1;
                $result['message'] = '预约成功';
                $result['to_url'] = $this->generateUrl('zm_member_order', array('status' => 1));
            } else {
                $result['code'] = 2;
                $result['message'] = '预约失败';
                $result['to_url'] = '';
            }
            return new JsonResponse($result);
        }

        $appliance_list = $this->get_appliance();

        $store = $conn->fetchAssoc("SELECT id,store_name,store_tel, phone FROM store WHERE id = ? LIMIT 1", array($store_id));

        return $this->render('ZMMemberBundle:Order:action.html.twig', array('appliance_list' => $appliance_list, 'store' => $store, 'province_list' => $this->get_province()));
    }

    public function reviewAction(Request $request, $id)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        $maintenance_info = $conn->fetchAssoc("SELECT m.id AS maintenance_id, m.is_review, s.id AS store_id, s.store_name, m.order_sn, m.fix_way, a.appliance_name, man.name AS manufacturer_name, m.date_added FROM "
            . "maintenance m LEFT JOIN store s ON m.store_id = s.id LEFT JOIN appliance a ON m.appliance_id = a.id LEFT JOIN app_man am ON am.id = m.manufacturer_id LEFT JOIN manufacturer man ON "
            . "am.manufacturer_id = man.id WHERE m.id = ? LIMIT 1", array($id));

        $maintenance_info['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE type = 0 AND store_id = ? LIMIT 1", array($maintenance_info['store_id']));
        if (empty($maintenance_info['image'])) {
            $maintenance_info['image'] = $this->default_store_image;
        }
        $review_info = $conn->fetchAssoc("SELECT id, is_share, evaluation_velocity, evaluation_quality, evaluation_attitude, evaluation_price, content, image FROM review WHERE maintenance_id = ? LIMIT 1", array($id));

        if (empty($review_info)) {
            $review_info = array('id' => 0, 'is_share' => 0, 'evaluation_velocity' => 0, 'evaluation_quality' => 0, 'evaluation_attitude' => 0, 'evaluation_price' => 0, 'content' => '', 'image' => '');
        }

        if (!empty($review_info['image'])) {
            $review_info['image'] = unserialize($review_info['image']);
        }

        $error = array();

        if ($request->getMethod() == 'POST') {
            // $maintenance_info = $conn->fetchAssoc("SELECT id, store_id, order_sn FROM maintenance WHERE user_id = ? AND id = ? AND status = 2", array($member_id, $id));
            $check_review = $conn->fetchColumn("SELECT id FROM review WHERE maintenance_id = ? LIMIT 1", array($id));

            if (!empty ($maintenance_info)) {
                $data['user_id'] = $member_id;
                $data['store_id'] = $maintenance_info['store_id'];
                if ($request->request->get('content')) {
                    $data['content'] = $request->request->get('content');
                }

                if ($request->request->get('evaluation_velocity')) {
                    $data['evaluation_velocity'] = $request->request->get('evaluation_velocity');
                }

                if ($request->request->get('evaluation_quality')) {
                    $data['evaluation_quality'] = $request->request->get('evaluation_quality');
                }

                if ($request->request->get('evaluation_attitude')) {
                    $data['evaluation_attitude'] = $request->request->get('evaluation_attitude');
                }

                if ($request->request->get('evaluation_price')) {
                    $data['evaluation_price'] = $request->request->get('evaluation_price');
                }

                $data['date_added'] = date('Y-m-d H:i:s');
                $data['maintenance_id'] = $id;

                $review_id = $request->request->get('review_id');

                $points = 0;

                if ((isset($data['evaluation_velocity'])) || (isset($data['evaluation_quality'])) || (isset($data['evaluation_attitude'])) || (isset($data['evaluation_price'])) && ($data['evaluation_velocity'] != 0 || $data['evaluation_quality'] != 0 || $data['evaluation_attitude'] != 0 || $data['evaluation_price'] != 0)) {
                    $points += 3;
                }

                if (isset($data['content']) && !empty($data['content'])) {
                    $points += 10;
                }

                if ((isset($data['content']) && empty($data['content'])) && (isset($data['evaluation_attitude']) && empty($data['evaluation_attitude'])) && (isset($data['evaluation_price']) && empty($data['evaluation_price'])) && (isset($data['evaluation_quality']) && empty($data['evaluation_quality'])) && (isset($data['evaluation_velocity']) && empty($data['evaluation_velocity'])) && $id == 0) {
                    $error['content'] = '不能全为空';
                    return $this->render('ZMMemberBundle:Order:review.html.twig', array('maintenance_info' => $maintenance_info, 'review_info' => $review_info, 'webIp' => $this->webIp, 'error' => $error));

                }

                if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {

                    $path = '../../kuaixiu-website/web/image/member/order/';

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
                            $image_name[$key] = $this->uploadPic('image', $path, $maintenance_info['order_sn'] . $key . uniqid());
                        }
                        $data['image'] = serialize($image_name);
                    }

                    if (!empty ($image_name)) {
                        $points += 5;
                    }

                }

                if ($review_id == 0 && empty ($check_review)) {
                    $row = $conn->insert('review', $data);
                    $review_id = $conn->lastInsertId();
                } else {
                    $conn->update('review', $data, array('id' => $review_id));
                }

                if (isset($row)) {

                    //更新会员积分
                    $res = $conn->executeUpdate("UPDATE user SET points = points + ? WHERE id = ?", array($points, $member_id));
                    //积分详情记录
                    $conn->insert('reward', array('user_id' => $member_id, 'points' => $points, 'description' => '订单评论', 'date_added' => date('Y-m-d H:i:s')));

                    //更新店铺评分
                    $store_grade = $conn->fetchColumn("SELECT grade FROM store WHERE id = ? LIMIT 1", array($data['store_id']));
                    $avg_grade = ($data['evaluation_velocity'] + $data['evaluation_quality'] + $data['evaluation_attitude'] + $data['evaluation_price']) / 4;
                    $store_grade_new = ($avg_grade + $store_grade) / 2;
                    $conn->update('store', array('grade' => $store_grade_new), array('id' => $data['store_id']));
                    $get_score = 0;
                    switch (intval($avg_grade)) {
                        case 1:
                            $get_score = '-2';
                            $conn->executeUpdate("UPDATE store SET score = score - 2 WHERE id = ?", array($data['store_id']));
                            break;
                        case 2:
                            $get_score = '-1';
                            $conn->executeUpdate("UPDATE store SET score = score - 1 WHERE id = ?", array($data['store_id']));
                            break;
                        case 4:
                            $get_score = '3';
                            $conn->executeUpdate("UPDATE store SET score = score + 3 WHERE id = ?", array($data['store_id']));
                            break;
                        case 5:
                            $get_score = '5';
                            $conn->executeUpdate("UPDATE store SET score = score + 5 WHERE id = ?", array($data['store_id']));
                            break;
                    }

                    $conn->insert('store_reward', array(
                        'store_id' => $maintenance_info['store_id'],
                        'points' => $get_score,
                        'description' => '订单积分',
                        'date_added' => date('Y-m-d H:i:s'),
                        'maintenance_id' => $id));

                    //如果计算后积分小于0, 设置为0
                    $store_score = $conn->fetchColumn("SELECT score FROM store WHERE id = ? LIMIT 1", array($data['store_id']));
                    if ($store_score < 0) {
                        $conn->update('store', array('score' => 0), array('id' => $data['store_id']));
                    }

                    //更新维修记录表评价状态
                    $conn->update('maintenance', array('is_review' => 1), array('id' => $id));
                }

                //跳转晒单页
                return $this->redirect($this->generateUrl('zm_member_order_show', array('review_id' => $review_id)));
            }
        }

        return $this->render('ZMMemberBundle:Order:review.html.twig', array('maintenance_info' => $maintenance_info, 'review_info' => $review_info, 'webIp' => $this->webIp, 'error' => $error));
    }

    public function cancelAction($id)
    {
        $member_id = $this->ajax_is_logged();

        if (!is_int($member_id)) {
            return $member_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $result['code'] = 0;

        $check = $conn->fetchAssoc("SELECT * FROM maintenance WHERE id = ? AND user_id = ? AND status = 1", array($id, $member_id));

        if ($check) {

            $result['code'] = 2;
            $result['message'] = '取消订单失败';

            $row = $conn->update('maintenance', array('status' => 0, 'date_canceled' => date('Y-m-d H:i:s'), 'verify' => 2), array('id' => $id));

            if ($row) {
                $result['code'] = 1;
                $result['message'] = '成功取消订单';
            }

        } else {
            $result['code'] = 2;
            $result['message'] = '取消订单失败';
        }

        return new JsonResponse($result);
    }

    public function completeAction($id)
    {
        $member_id = $this->ajax_is_logged();

        if (!is_int($member_id)) {
            return $member_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $result['code'] = 0;

        $check = $conn->fetchAssoc("SELECT * FROM maintenance WHERE id = ? AND user_id = ? AND status = 3", array($id, $member_id));

        if ($check) {

            $row = $conn->update('maintenance', array('status' => 2, 'date_completed' => date('Y-m-d H:i:s')), array('id' => $id));

            if ($row) {
                $result['code'] = 1;
                $result['message'] = '成功完成订单';

                $conn->executeUpdate("UPDATE store SET order_num = order_num + 1 WHERE id = ?", array($check['store_id']));
            } else {
                $result['code'] = 2;
                $result['message'] = '完成订单失败';
            }
        } else {
            $result['code'] = 2;
            $result['message'] = '操作失败';
        }

        return new JsonResponse($result);
    }

    public function showAction(Request $request, $review_id)
    {
        $error['content'] = '';

        $conn = $this->getDoctrine()->getConnection();

        $review = $conn->fetchAssoc("SELECT store_id, image, maintenance_id FROM review WHERE id = ? LIMIT 1", array($review_id));
        
        if ($request->getMethod() == 'POST') {
            $show = $request->request->get('show');

            if ($show == 1) {

                if (!empty ($review['image'])) {
                    $conn->update('review', array('is_share' => 1), array('id' => $review_id));
                    return $this->redirect($this->generateUrl('zm_member_store_review', array('store_id' => $review['store_id'])));
                } else {
                    return $this->redirect($this->generateUrl('zm_member_order_review', array('id' => $review['maintenance_id'])));
                }
            }
        }

        return $this->render('ZMMemberBundle:Order:show.html.twig', array('maintenance_id' => $review['maintenance_id'], 'error' => $error, 'review_id' => $review_id));
    }
}
