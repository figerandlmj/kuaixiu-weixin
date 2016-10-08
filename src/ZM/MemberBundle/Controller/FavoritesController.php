<?php

namespace ZM\MemberBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FavoritesController extends BaseController
{
    public function indexAction(Request $request)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->redirect($this->generateUrl('zm_member_login'));
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($request->query->has('page') && (int) $request->query->get('page')) {
            $page = (int) $request->query->get('page');
        } else {
            $page = 1;
        }

        $limit = 20;

        $start = ($page - 1) * $limit;

        $total = $conn->fetchColumn("SELECT COUNT(f.id) FROM favorites f LEFT JOIN user u ON f.user_id = u.id LEFT JOIN store s ON f.store_id = s.id "
            . "WHERE f.user_id = ?", array($member_id));

        $favorites_list = $conn->fetchAll("SELECT s.id, s.store_name, sg.level, s.order_num, s.grade FROM favorites f LEFT JOIN user u ON f.user_id = u.id LEFT JOIN store s ON f.store_id = s.id LEFT JOIN store_group sg ON s.store_group_id = sg.id "
            . "WHERE f.user_id = ? LIMIT $start, $limit", array($member_id));
        foreach ($favorites_list as $key => $value) {
            $favorites_list[$key]['image'] = $conn->fetchColumn("SELECT image FROM store_qualification WHERE store_id = ? AND type = 0 LIMIT 1", array($value['id']));
            if (empty($favorites_list[$key]['image'])) {
                $favorites_list[$key]['image'] = $this->default_store_image;
            }
        }
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->generateUrl('zm_member_favorites') . '?page={page}';

        $render = $pagination->render();

        $url = $this->generateUrl('zm_member_favorites') . '?page=' . $page;

        return $this->render('ZMMemberBundle:Favorites:index.html.twig', array('favorites_list' => $favorites_list, 'render' => $render, 'url' => $url, 'webIp' => $this->webIp));
    }

    /**
     * 添加，取消收藏
     *
     * @param type $id 店铺id
     * @param type $type 1:添加，2.取消
     * @return Response
     */
    public function actionAjaxAction($id, $type)
    {
        $member_id = $this->getMySession('member_id');

        if (empty($member_id)) {
            return $this->ajax_is_logged();
        }

        $conn = $this->getDoctrine()->getConnection();

        if ($type == 1) {
            $favorites_info = $conn->fetchAssoc("SELECT * FROM favorites WHERE user_id = ? AND store_id = ? LIMIT 1", array($member_id, $id));

            if (empty($favorites_info)) {
                $conn->insert('favorites', array('user_id' => $member_id, 'store_id' => $id, 'date_added' => date('Y-m-d H:i:s')));
                $result['code'] = 1;
                $result['message'] = '添加收藏成功';
            } else {
                $result['code'] = 2;
                $result['message'] = '不能重复收藏';
            }
        } else if ($type == 2) {
            $conn->delete('favorites', array('user_id' => $member_id, 'store_id' => $id));
            $result['code'] = 3;
            $result['message'] = '取消收藏成功';
        }

        return new JsonResponse($result);
    }
}
