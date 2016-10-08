<?php

/**
 * 投诉
 */

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\Request;

class ComplainController extends BaseController
{
    public function indexAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $where = ' WHERE c.user_id = ?';
        $url = '';

        if ($request->query->has('status') && $request->query->get('status') != '') {
            $filter['status'] = (int) $request->query->get('status');
            $where .= " AND c.status = " . $filter['status'];
            $url .= '&status=' . $filter['status'];
        } else {
            $filter['status'] = '';
        }

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $conn = $this->getDoctrine()->getConnection();

        $limit = 6;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(c.id) FROM complain c" . $where, array($member_id));

        $data = $conn->fetchAll("SELECT c.id, s.id AS store_id, s.store_name, m.order_sn, c.type, c.description, c.date_added, c.status FROM complain c LEFT JOIN store s ON c.store_id = s.id LEFT JOIN maintenance m ON c.maintenance_id = m.id " . $where
            . " ORDER BY c.id DESC LIMIT $start, $limit", array($member_id));
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
        $pagination->url = $this->generateUrl('zm_member_complain') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_complain') . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Complain:index.html.twig', array('data' => $data, 'render' => $render, 'url' => $url, 'webIp' => $this->webIp));
    }

    public function actionAction(Request $request, $maintenance_id)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        $maintenance = $conn->fetchAssoc("SELECT * FROM maintenance WHERE id = ? LIMIT 1", array($maintenance_id));
        if ($request->getMethod() == 'POST') {

            $data['maintenance_id'] = $maintenance_id;
            $data['type'] = $request->request->get('type');
            $data['description'] = $request->request->get('description');
            $data['date_added'] = date('Y-m-d H:i:s');
            $data['status'] = 0;
            $data['store_id'] = $maintenance['store_id'];
            $data['user_id'] = $member_id;

            $conn->insert('complain', $data);

            return $this->redirect($this->generateUrl('zm_member_complain'));
        }

        return $this->render('ZMMemberBundle:Complain:action.html.twig', array('maintenance' => $maintenance));
    }
}
