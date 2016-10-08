<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class EmployController extends BaseController
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

        if ($request->query->get('status') != '') {
            $filter['status'] = (int) $request->query->get('status');
            $where .= " AND status = " . $filter['status'];
            $url .= '&status=' . $filter['status'];
        } else {
            $filter['status'] = '';
        }

        if ($request->query->get('display') != '') {
            $filter['display'] = (int) $request->query->get('display');
            $where .= " AND display = " . $filter['display'];
            $url .= '&display=' . $filter['display'];
        } else {
            $filter['display'] = '';
        }

        $conn = $this->getDoctrine()->getConnection();

        $limit = 6;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM store_employ " . $where);
        $data = $conn->fetchAll("SELECT * FROM store_employ " . $where . " ORDER BY id DESC LIMIT $start, $limit");

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_store_employ') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_store_employ') . '?page=' . $page . $url;

        return $this->render('ZMStoreBundle:Employ:index.html.twig', array('data' => $data, 'render' => $render, 'filter' => $filter));
    }

    public function infoAction(Request $request, $id)
    {
        $store_id = $this->is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $conn = $this->getDoctrine()->getConnection();

        $employ = $conn->fetchAssoc("SELECT * FROM store_employ WHERE store_id = ? AND id = ? LIMIT 1", array($store_id, $id));

        if ($request->getMethod() == 'POST') {
            $data['store_id'] = $store_id;
            $data['position'] = $request->request->get('position');
            $data['employ_num'] = $request->request->get('employ_num');
            $data['requirement'] = $request->request->get('requirement');
            $data['treatment'] = $request->request->get('treatment');
            $data['address'] = $request->request->get('address');
            $data['telephone'] = $request->request->get('telephone');
            $data['link_man'] = $request->request->get('link_man');
            $data['date_added'] = date('Y-m-d H:i:s');

            if ($id) {
                $conn->update('store_employ', $data, array('id' => $id));
                return $this->redirect($this->generateUrl('zm_store_employ') . '?status=' . $employ['status'] .'&display=' . $employ['display']);
            } else {
                $conn->insert('store_employ', $data);
                return $this->redirect($this->generateUrl('zm_store_employ') . '?status=0&display=1');
            }

        }

        return $this->render('ZMStoreBundle:Employ:info.html.twig', array('employ' => $employ));
    }

    public function actionAjaxAction(Request $request)
    {
        $store_id = $this->ajax_is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $id = $request->request->get('id');
        $status = $request->request->get('status');

        $conn = $this->getDoctrine()->getConnection();

        $employ = $conn->fetchAssoc("SELECT * FROM store_employ WHERE store_id = ? AND id = ? LIMIT 1", array($store_id, $id));

        if ($employ) {
            $row = $conn->update('store_employ', array('display' => $status), array('id' => $id, 'store_id' => $store_id));

            if ($row && $status == 0) {
                $result['flag'] = 1;
                $result['content'] = '删除成功';
            } else if (empty($row) && $status == 0) {
                $result['flag'] = 2;
                $result['content'] = '删除失败';
            } else if ($row && $status == 1) {
                $result['flag'] = 3;
                $result['content'] = '恢复成功';
            } else if (empty($row) && $status == 1) {
                $result['flag'] = 4;
                $result['content'] = '恢复失败';
            } else {
                $result['flag'] = 5;
                $result['content'] = '非法参数';
            }
        } else {
            $result['flag'] = 5;
            $result['content'] = '非法参数';
        }

        return new JsonResponse($result);
    }
}
