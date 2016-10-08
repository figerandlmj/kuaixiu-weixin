<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class IndexController extends BaseController
{
    public function indexAction(Request $request)
    {
        $location_city = $this->get_city_id();

        $conn = $this->getDoctrine()->getConnection();

        if ($request->query->has('city_id') && $request->query->get('city_id')) {
            $location_city = $request->query->get('city_id');
            $city_name = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($location_city));
            $this->setMySession(array('member_city_id' => $location_city, 'member_city_name' => $city_name));
        }

        //首页banner
        $banner = $conn->fetchAll("SELECT * FROM banner ORDER BY id DESC");

        $store = $conn->fetchAll(" SELECT s.id, s.store_name, s.order_num, s.grade, s.title, s.store_tel, s.phone, s.address, s.type, sg.name AS group_name FROM store s LEFT JOIN store_group sg ON sg.id = s.store_group_id WHERE s.type = 2 AND s.status = 1 AND res_city_id = ? ORDER BY sg.level DESC, s.grade DESC, s.order_num DESC, s.id ASC LIMIT 12", array($location_city));
        if ($store) {
            foreach ($store as $key => $val) {
                $store[$key]['img'] = $conn->fetchColumn("SELECT image FROM store_qualification  WHERE type = 0 AND store_id = ? ", array($val['id']));
                if (empty($store[$key]['img'])) {
//                    $store[$key]['img'] = $this->default_store_image;
                    $store[$key]['img'] = '';
                }
            }
        }

        //民间高手
        $personal = $conn->fetchAll("SELECT * FROM store WHERE type = 1 AND status = 1 AND res_city_id = ? ORDER BY id DESC LIMIT 6", array($location_city));

        //明星产品
        $time = date('Y-m-d');
//        $star_product = $conn->fetchAll("SELECT man.id, man.name, sp.image FROM star_product sp LEFT JOIN app_man am ON sp.manufacturer_id = am.id LEFT JOIN manufacturer man ON man.id = am.manufacturer_id WHERE sp.deadline >= " . $time . " GROUP BY man.id ORDER BY sp.id ASC LIMIT 6");
//        if (!empty($star_product)) {
//            foreach ($star_product as $k => $val) {
//                $star_product[$k] = $val;
//                $star_product[$k]['num'] = $k;
//                $star_product[$k]['product'] = $conn->fetchAll(" SELECT sp.*, man.name, a.appliance_name FROM star_product sp LEFT JOIN app_man am ON am.id = sp.manufacturer_id LEFT JOIN appliance a ON a.id = sp.appliance_id LEFT JOIN manufacturer man ON man.id = am.manufacturer_id WHERE man.id = ? ORDER BY sp.price DESC , sp.sort DESC LIMIT 9", array($val['id']));
//            }
//        }

        $star_product = $conn->fetchAll("SELECT sp.*, man.name, a.appliance_name FROM star_product sp LEFT JOIN app_man am ON am.id = sp.manufacturer_id LEFT JOIN appliance a ON a.id = sp.appliance_id LEFT JOIN manufacturer man ON man.id = am.manufacturer_id WHERE sp.deadline >= " . $time . " ORDER BY sp.price DESC, sp.sort DESC LIMIT 4");

        return $this->render('ZMMemberBundle:Index:index.html.twig', array('banner' => $banner, 'store' => $store, 'personal' => $personal, 'star_product' => $star_product, 'webIp' => $this->webIp, 'province_list' => $this->get_province()));
    }

    public function personalAction(Request $request)
    {
        $location_city = $this->get_city_id();

        $conn = $this->getDoctrine()->getConnection();

        $url = '';

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $limit = 6;

        $start = ($page - 1) * $limit;

        $where = ' WHERE s.type = 1 AND s.status = 1 ';
        $url = '';
        $sql = '';

        if ($request->query->has('res_city_id') && $request->query->get('res_city_id')) {
            $filter['res_city_id'] = $request->query->get('res_city_id');
            $location_city = $filter['res_city_id'];

            $city_name = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($location_city));
            $this->setMySession(array('member_city_id' => $filter['res_city_id'], 'member_city_name' => $city_name));

            $where .= " AND s.res_city_id = " . $filter['res_city_id'];
            $url .= '&res_city_id=' . $filter['res_city_id'];
        } else {
            $filter['res_city_id'] = $location_city;
            $where .= " AND s.res_city_id = " . $location_city;
        }

        if ($request->query->has('res_district_id') && $request->query->get('res_district_id')) {
            $filter['res_district_id'] = $request->query->get('res_district_id');
            $where .= " AND s.res_district_id = " . $filter['res_district_id'];
            $url .= '&res_district_id=' . $filter['res_district_id'];
        } else {
            $filter['res_district_id'] = '';
        }

        if ($request->query->has('appliance_id') && $request->query->get('appliance_id')) {
            $filter['appliance_id'] = $request->query->get('appliance_id');
            $where .= " AND sa.appliance_id = " . $filter['appliance_id'];
            $sql = ' LEFT JOIN store_appliance sa ON s.id = sa.store_id LEFT JOIN appliance a ON sa.appliance_id = a.id';
            $url .= '&appliance_id=' . $filter['appliance_id'];
        } else {
            $filter['appliance_id'] = '';
        }

        if ($request->query->has('store_name') && $request->query->get('store_name')) {
            $filter['store_name'] = $request->query->get('store_name');
            $where .= " AND s.store_name LIKE '%" . urldecode($filter['store_name']) . "%'";
            $url .= '&store_name=' . $filter['store_name'];
        } else {
            $filter['store_name'] = '';
        }

        if ($request->query->has('is_near') && $request->query->get('is_near') == 1) {

            $filter['latitude'] = $request->query->get('member_latitude') ? $request->query->get('member_latitude') : $this->getMysession('member_latitude');
            $filter['longitude'] = $request->query->get('member_longitude') ? $request->query->get('member_longitude') : $this->getMysession('member_longitude');
//            echo $filter['latitude'] . '==' . $filter['longitude'];
            $where .= " AND (s.latitude BETWEEN " . ($filter['latitude'] - $this->latitude_diff(30)) . " AND " . ($filter['latitude'] + $this->latitude_diff(30)) . ")" .
                " AND (s.longitude BETWEEN " . ($filter['longitude'] - $this->longitude_diff($filter['latitude'], 30)) . " AND " . ($filter['longitude'] + $this->longitude_diff($filter['latitude'], 30)) . ")";
//            $url .= '&is_near=' . $request->query->get('is_near');
        }

        if ($request->query->has('order') && $request->query->get('order')) {
            $filter['order'] = $request->query->get('order');
            $order = ' s.' . $filter['order'] . ' DESC';
//            $url .= '&order=' . $filter['order'];
        } else {
            $filter['order'] = '';
            $order = " s.dues_day_avg DESC, sg.level DESC, s.id";
        }

        //民间高手
        $personal = $conn->fetchAll("SELECT s.*, sg.level FROM store s LEFT JOIN store_group sg ON s.store_group_id = sg.id " . $sql . $where . " ORDER BY " . $order . " LIMIT $start, $limit");

        foreach ($personal as $key => $value) {
            $personal[$key]['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE type = 0 AND store_id = ? LIMIT 1", array($value['id']));
            if (empty($personal[$key]['image'])) {
                $personal[$key]['image'] = $this->default_store_personal;
            }
        }
        $total = $conn->fetchColumn("SELECT COUNT(s.id) FROM store s " . $sql . $where);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_index_personal') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_index_personal') . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Index:personal.html.twig', array('appliance_list' => $this->get_appliance(), 'personal' => $personal, 'render' => $render, 'url' => $url, 'city_id' => $location_city, 'filter' => $filter, 'webIp' => $this->webIp));
    }

    public function starAction()
    {
        $conn = $this->getDoctrine()->getConnection();

        //明星产品
        $time = date('Y-m-d');
        $star_product = $conn->fetchAll("SELECT man.id, man.name, sp.image FROM star_product sp LEFT JOIN app_man am ON sp.manufacturer_id = am.id LEFT JOIN manufacturer man ON man.id = am.manufacturer_id WHERE sp.deadline >= " . $time . " GROUP BY man.id ORDER BY sp.id ASC");
        if (!empty($star_product)) {
            foreach ($star_product as $k => $val) {
                $star_product[$k] = $val;
                $star_product[$k]['num'] = $k;
                $star_product[$k]['product'] = $conn->fetchAll("SELECT sp.*, man.name, a.appliance_name FROM star_product sp LEFT JOIN app_man am ON am.id = sp.manufacturer_id LEFT JOIN appliance a ON a.id = sp.appliance_id LEFT JOIN manufacturer man ON man.id = am.manufacturer_id WHERE man.id = ? ORDER BY sp.price DESC , sp.sort DESC", array($val['id']));
            }
        }

        return $this->render('ZMMemberBundle:Index:star.html.twig', array('star_product' => $star_product, 'webIp' => $this->webIp));
    }

    public function searchAction(Request $request)
    {
        $location_city = $this->get_city_id();

        $where = ' WHERE s.type = 2 AND s.status = 1 ';
        $url = '';
        $sql = '';

        $conn = $this->getDoctrine()->getConnection();

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        if ($request->query->has('res_city_id') && $request->query->get('res_city_id')) {
            $filter['res_city_id'] = $request->query->get('res_city_id');
            $location_city = $filter['res_city_id'];

            $city_name = $conn->fetchColumn("SELECT name FROM res_city WHERE id = ? LIMIT 1", array($location_city));
            $this->setMySession(array('member_city_id' => $filter['res_city_id'], 'member_city_name' => $city_name));

            $where .= " AND s.res_city_id = " . $filter['res_city_id'];
            $url .= '&res_city_id=' . $filter['res_city_id'];
        } else {
            $filter['res_city_id'] = $location_city;
            $where .= " AND s.res_city_id = " . $location_city;
        }

        if ($request->query->has('res_district_id') && $request->query->get('res_district_id')) {
            $filter['res_district_id'] = $request->query->get('res_district_id');
            $where .= " AND s.res_district_id = " . $filter['res_district_id'];
            $url .= '&res_district_id=' . $filter['res_district_id'];
        } else {
            $filter['res_district_id'] = '';
        }

        if ($request->query->has('type') && $request->query->get('type')) {
            $filter['type'] = $request->query->get('type');
            $where .= " AND s.type = " . $filter['type'];
            $url .= '&type=' . $filter['type'];
        } else {
            $filter['type'] = '';
        }

        if ($request->query->has('appliance_id') && $request->query->get('appliance_id')) {
            $filter['appliance_id'] = $request->query->get('appliance_id');
            $where .= " AND sa.appliance_id = " . $filter['appliance_id'];
            $sql = ' LEFT JOIN store_appliance sa ON s.id = sa.store_id LEFT JOIN appliance a ON sa.appliance_id = a.id';
            $url .= '&appliance_id=' . $filter['appliance_id'];
        } else {
            $filter['appliance_id'] = '';
        }

        if ($request->query->has('appliance_name') && $request->query->get('appliance_name')) {
            $filter['appliance_name'] = $request->query->get('appliance_name');
            $where .= " AND a.appliance_name LIKE '%" . urldecode($filter['appliance_name']) . "%'";
            $sql = ' LEFT JOIN store_appliance sa ON s.id = sa.store_id LEFT JOIN appliance a ON sa.appliance_id = a.id';
            $url .= '&appliance_name=' . $filter['appliance_name'];
        } else {
            $filter['appliance_name'] = '';
        }

        if ($request->query->has('store_name') && $request->query->get('store_name')) {
            $filter['store_name'] = $request->query->get('store_name');
            $where .= " AND s.store_name LIKE '%" . urldecode($filter['store_name']) . "%'";
            $url .= '&store_name=' . $filter['store_name'];
        } else {
            $filter['store_name'] = '';
        }

        if ($request->query->has('is_near') && $request->query->get('is_near') == 1) {

            $filter['latitude'] = $request->query->get('member_latitude') ? $request->query->get('member_latitude') : $this->getMysession('member_latitude');
            $filter['longitude'] = $request->query->get('member_longitude') ? $request->query->get('member_longitude') : $this->getMysession('member_longitude');
//            echo $filter['latitude'] . '==' . $filter['longitude'];
            $where .= " AND (s.latitude BETWEEN " . ($filter['latitude'] - $this->latitude_diff(30)) . " AND " . ($filter['latitude'] + $this->latitude_diff(30)) . ")" .
                " AND (s.longitude BETWEEN " . ($filter['longitude'] - $this->longitude_diff($filter['latitude'], 30)) . " AND " . ($filter['longitude'] + $this->longitude_diff($filter['latitude'], 30)) . ")";
//            $url .= '&is_near=' . $request->query->get('is_near');
        }

        if ($request->query->has('order') && $request->query->get('order')) {
            $filter['order'] = $request->query->get('order');
            $order = ' s.' . $filter['order'] . ' DESC';
//            $url .= '&order=' . $filter['order'];
        } else {
            $filter['order'] = '';
            $order = " s.dues_day_avg DESC, sg.level DESC, s.id";
        }

        $limit = 6;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(s.id) FROM store s LEFT JOIN store_group sg ON sg.id = s.store_group_id " . $sql . $where);
        $data = $conn->fetchAll("SELECT s.id, s.store_name, s.grade, s.order_num, s.res_city_id, s.type, s.status, s.latitude, s.longitude, sg.level FROM store s LEFT JOIN store_group sg ON sg.id = s.store_group_id " . $sql . $where . " ORDER BY " .  $order . " LIMIT $start, $limit");

        foreach ($data as $key => $value) {
            $data[$key]['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE store_id = ? AND type = 0 LIMIT 1", array($value['id']));
            if ($data[$key]['image'] == '') {
                $data[$key]['image'] = $this->default_store_image;
            }
        }

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_index_search') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_index_search') . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Index:search.html.twig', array('appliance_list' => $this->get_appliance(), 'data' => $data, 'render' => $render, 'filter' => $filter, 'url' => $url, 'webIp' => $this->webIp, 'city_id' => $location_city, 'province_list' => $this->get_province()));
    }
}
