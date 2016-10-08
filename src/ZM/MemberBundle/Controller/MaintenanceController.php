<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class MaintenanceController extends BaseController
{
    public function indexAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $url = '';

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $limit = 6;

        $start = ($page - 1) * $limit;

        $conn = $this->getDoctrine()->getConnection();

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM maintenance WHERE user_id = ?", array($member_id));

        $data = $conn->fetchAll("SELECT m.id, s.id AS store_id, s.store_name, s.phone, s.store_tel, man.name AS manufacturer_name, a.appliance_name, m.description, m.order_sn, m.date_added, m.status FROM "
            . "maintenance m LEFT JOIN store s ON m.store_id = s.id LEFT JOIN app_man am ON am.id = m.manufacturer_id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id LEFT JOIN "
            . "appliance a ON m.appliance_id = a.id WHERE m.user_id = ? LIMIT $start, $limit", array($member_id));

        foreach ($data as $key => $value) {
            $data[$key]['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE type = 0 AND store_id = ? LIMIT 1", array($value['store_id']));
            if (empty($data[$key]['image'])) {
                $data[$key]['image'] = $this->default_store_image;
            }
        }
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_maintenance') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_maintenance') . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Maintenance:index.html.twig', array('data' => $data, 'render' => $render, 'url' => $url, 'webIp' => $this->webIp));
    }
}
