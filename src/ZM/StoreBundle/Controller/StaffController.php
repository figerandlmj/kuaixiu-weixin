<?php

namespace ZM\StoreBundle\Controller;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class StaffController extends BaseController
{
    public function indexAction(Request $request)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $url = '';
        $where = " WHERE store_id = " . $store_id;
        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $limit = 6;

        $start = ($page - 1) * $limit;

        $conn = $this->getDoctrine()->getConnection();

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM store_staff " . $where);
        $data = $conn->fetchAll("SELECT * FROM store_staff " . $where . " ORDER BY id DESC LIMIT $start, $limit");

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_store_staff') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_store_staff') . '?page=' . $page . $url;

        return $this->render('ZMStoreBundle:Staff:index.html.twig', array('data' => $data, 'render' => $render));
    }

    public function infoAction(Request $request, $id)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->getMethod() == 'POST') {

            $data['name'] = $request->request->get('name');
            $data['telephone'] = $request->request->get('telephone');

            if ($id) {
                $conn->update('store_staff', $data, array('id' => $id));
            } else {
                $conn->insert('store_staff', $data);
            }

            return $this->redirect($this->generateUrl('zm_store_staff'));
        }

        $staff = $conn->fetchAssoc("SELECT * FROM store_staff WHERE id = ? AND store_id = ? LIMIT 1", array($id, $store_id));

        return $this->render('ZMStoreBundle:Staff:info.html.twig', array('staff' => $staff));
    }

    public function deleteAjaxAction(Request $request)
    {
        $store_id = $this->ajax_is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $id = $request->request->get('id');

        $row = $conn->delete('store_staff', array('id' => $id, 'store_id' => $store_id));

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
