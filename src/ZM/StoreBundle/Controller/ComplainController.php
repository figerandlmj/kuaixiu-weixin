<?php

namespace ZM\StoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ComplainController extends BaseController
{
    public function indexAction(Request $request)
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

        $total = $conn->fetchColumn("SELECT COUNT(id) FROM complain WHERE store_id = ?", array($store_id));
        $data = $conn->fetchAll("SELECT c.*, ua.phone, m.order_sn, a.appliance_name, man.name AS manufacturer_name FROM complain c LEFT JOIN maintenance m ON m.id = c.maintenance_id LEFT JOIN user_address ua ON m.address_id = ua.id LEFT JOIN appliance a ON m.appliance_id = a.id LEFT JOIN app_man am ON am.id = m.manufacturer_id LEFT JOIN manufacturer man ON am.manufacturer_id = man.id WHERE c.store_id = ? ORDER BY c.date_added DESC LIMIT $start, $limit", array($store_id));

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_store_complain') . '?page={page}' . $url;

        $render = $pagination->render();

        $url = $this->generateUrl('zm_store_complain') . '?page=' . $page . $url;

        return $this->render('ZMStoreBundle:Complain:index.html.twig', array('data' => $data, 'render' => $render));
    }

    public function actionAjaxAction(Request $request)
    {
        $store_id = $this->ajax_is_logged();
        if (!is_int($store_id)) {
            return $store_id;
        }

        $id = $request->request->get('id');

        $conn = $this->getDoctrine()->getConnection();

        $row = $conn->update('complain', array('status' => 1), array('id' => $id));

        if ($row) {
            $result['flag'] = 1;
            $result['content'] = '处理成功';
        } else {
            $result['flag'] = 2;
            $result['content'] = '处理失败';
        }
        

        return new JsonResponse($result);
    }
}
