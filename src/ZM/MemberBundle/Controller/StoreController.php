<?php

namespace ZM\MemberBundle\Controller;

class StoreController extends BaseController
{
    public function infoAction($id)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            $member_id = 0;
        }

        $conn = $this->getDoctrine()->getConnection();

        $store = $conn->fetchAssoc("SELECT s.id AS store_id, s.phone, s.store_tel, s.store_name, sg.name, sg.level, s.order_num, rp.name, s.start_week, s.start_time, s.end_week, s.end_time,rp.name AS res_province_name, rc.name AS res_city_name, rd.name AS res_district_name, s.address, s.grade FROM store s LEFT JOIN res_province rp ON s.res_province_id = rp.id LEFT JOIN res_city rc ON s.res_city_id = rc.id LEFT JOIN res_district rd ON s.res_district_id = rd.id LEFT JOIN store_group sg ON s.store_group_id = sg.id WHERE s.id = ?", array($id));

        $store['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE type = 0 AND store_id = ? LIMIT 1", array($store['store_id']));
        if (empty($store['image'])) {
            $store['image'] = $this->default_store_personal;
        }

        $appliance = $conn->fetchAll("SELECT a.id, a.appliance_name FROM store_appliance sa LEFT JOIN appliance a ON sa.appliance_id = a.id WHERE sa.store_id = ?", array($id));

        $review = $conn->fetchAll("SELECT id, (evaluation_velocity + evaluation_quality + evaluation_attitude + evaluation_price) / 4 AS evaluation, content, date_added FROM review WHERE store_id = ? ORDER BY id DESC", array($id));
        $review_total = $conn->fetchColumn("SELECT COUNT(id) FROM review WHERE store_id = ?", array($id));
        $is_favorites = 0;
        if ($member_id != 0) {
            $row = $conn->fetchColumn("SELECT id FROM favorites WHERE user_id = ? AND store_id = ? LIMIT 1", array($member_id, $id));
            if ($row) {
                $is_favorites = 1;
            }
        }

        $special = $conn->fetchAll("SELECT s.id, a.appliance_name, man.name AS manufacturer_name FROM sign s LEFT JOIN appliance a ON s.appliance_id = a.id LEFT JOIN app_man am ON am.id = s.manufacturer_id "
            . "LEFT JOIN manufacturer man ON am.manufacturer_id = man.id WHERE s.store_id = ?" ,array($id));

        return $this->render('ZMMemberBundle:Store:info.html.twig', array('store' => $store, 'appliance' => $appliance , 'special' => $special, 'review' => $review, 'review_total' => $review_total, 'is_favorites' => $is_favorites, 'webIp' => $this->webIp));
    }

    public function reviewAction($store_id)
    {
        $conn = $this->getDoctrine()->getConnection();

        $review = $conn->fetchAll("SELECT r.id, u.phone, u.avatar, (r.evaluation_velocity + r.evaluation_quality + r.evaluation_attitude + r.evaluation_price) / 4 AS evaluation, r.date_added, r.content, r.image FROM review r LEFT JOIN user u ON r.user_id = u.id WHERE r.store_id = ? ORDER BY r.date_added DESC", array($store_id));

        if ($review) {
            foreach ($review as $key => $value) {
                $review[$key]['image'] = unserialize($value['image']);
            }
        }

        return $this->render('ZMMemberBundle:Store:review.html.twig', array('review' => $review, 'webIp' => $this->webIp));
    }
}
