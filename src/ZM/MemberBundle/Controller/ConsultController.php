<?php

/**
 * 咨询
 */

namespace ZM\MemberBundle\Controller;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ConsultController extends BaseController
{
    public function indexAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $where = ' WHERE user_id = ' . $member_id;
        $url = '';

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $conn = $this->getDoctrine()->getConnection();

        $limit = 6;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM consult " . $where);
        $data = $conn->fetchAll("SELECT * FROM consult " . $where . " ORDER BY id DESC LIMIT $start, $limit");

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_consult') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_consult') . '?page=' . $page . $url;

        return $this->render('ZMMemberBundle:Consult:index.html.twig', array('data' => $data, 'render' => $render));
    }

    public function infoAction(Request $request, $id, $type=0)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {
            $data['type'] = $request->request->get('type');
            $data['content'] = $request->request->get('content');
            $data['date_added'] = date('Y-m-d H:i:s');
            $data['user_id'] = $member_id;

            $conn->insert('consult', $data);

            return $this->redirect($this->generateUrl('zm_member_consult'));
        }

        $consult = $conn->fetchAssoc("SELECT * FROM consult WHERE id = ? LIMIT 1", array($id));

        return $this->render('ZMMemberBundle:Consult:info.html.twig', array('consult' => $consult, 'type' => $type));
    }

    public function typeAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $data = array('1' => '维修咨询', '2' => '保养咨询', '3' => '安装咨询', '4' => '家电价格');

        if ($request->getMethod() == 'POST') {
            $type = (int) $request->request->get('type');

            return $this->redirect($this->generateUrl('zm_member_consult_info', array('id' => 0, 'type' => $type)));
        }


        return $this->render('ZMMemberBundle:Consult:type.html.twig', array('data' => $data));
    }

    public function deleteAjaxAction(Request $request)
    {
        $member_id = $this->ajax_is_logged();
        if (!is_int($member_id)) {
            return $member_id;
        }

        $id = $request->request->get('id');

        $conn = $this->getDoctrine()->getConnection();

        $row = $conn->delete('consult', array('id' => $id));

        if ($row) {
            $result['flag'] = 1;
            $result['content'] = '删除成功';
        } else {
            $result['flag'] = 2;
            $result['content'] = '删除失败';
        }

        return new JsonResponse($result);
    }
}
