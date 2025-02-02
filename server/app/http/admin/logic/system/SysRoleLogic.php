<?php

namespace app\http\admin\logic\system;

use app\common\model\sys\SysAdminRoleModel;
use app\common\model\sys\SysRoleMenuModel;
use app\common\model\sys\SysRoleModel;
use support\Db;
use support\exception\RespBusinessException;

class SysRoleLogic
{
    /**
     * 角色列表
     * @param array $param
     * @return array
     */
    public static function list(array $param): array
    {
        $params = setQueryDefaultValue($param, [
            'name' => null,
            'value' => null,
            'status' => null,
            'remark' => null,
        ]);
        $filter = [];
        !is_null($params['name']) && $filter[] = ['name', 'like', "%{$params['name']}%"];
        !is_null($params['value']) && $filter[] = ['value', 'like', "%{$params['value']}%"];
        !is_null($params['status']) && $filter[] = ['status', '=', $params['status']];
        !is_null($params['remark']) && $filter[] = ['remark', 'like', "%{$params['remark']}%"];
        $list = SysRoleModel::query()->where($filter)->paginate($params['pageSize']);
        return formattedPaginate($list);
    }


    /**
     * 创建角色
     * @param array $params
     * @return true
     * @throws RespBusinessException
     */
    public static function create(array $params): bool
    {
        try {
            // 检查 角色名称是否重复 角色值是否重复
            if (SysRoleModel::checkExists((new SysRoleModel), ['name' => $params['name']]) || SysRoleModel::checkExists((new SysRoleModel), ['value' => $params['value']])) {
                throw new \Exception('角色名称或角色值重复');
            }
            DB::transaction(function () use ($params) {
                // 先提交创建角色 在提交角色菜单关联
                $roleId = SysRoleModel::insertGetId(
                    [
                        'name' => $params['name'],
                        'remark' => $params['remark'],
                        'status' => $params['status'],
                        'value' => $params['value'],
                        'created_at' => time(),
                        'updated_at' => time(),
                    ]
                );
                $menuIds = $params['menuIds'];
                $save = array_map(function ($menuId) use ($roleId) {
                    return ['menu_id' => $menuId, 'role_id' => $roleId];
                }, $menuIds);
                SysRoleMenuModel::insert($save);
            });
            return true;
        } catch (\Exception $e) {
            throw new RespBusinessException($e->getMessage());
        }
    }


    /**
     * 更新角色
     * @param array $params
     * @return bool
     * @throws RespBusinessException
     */
    public static function update(array $params): bool
    {
        try {
            // 检查 角色名称是否重复 角色值是否重复
            if (SysRoleModel::checkExists((new SysRoleModel), [['name', '=', $params['name']], ['id', '<>', $params['id']]]) || SysRoleModel::checkExists((new SysRoleModel), [['value', '=', $params['value']], ['id', '<>', $params['id']]])) {
                throw new \Exception('角色名称或角色值重复');
            }

            DB::transaction(function () use ($params) {
                SysRoleModel::query()->where('id', $params['id'])->update(
                    [
                        'name' => $params['name'],
                        'remark' => $params['remark'],
                        'status' => $params['status'],
                        'value' => $params['value'],
                        'updated_at' => time(),
                    ]
                );

                // 查询以前的 menuIds
                $oldMenuIds = SysRoleMenuModel::query()->where('role_id', $params['id'])->pluck('menu_id')->toArray();
                // 前端传递的 menuIds
                $menuIds = $params['menuIds'];
                // 找出需要删除的 menuIds
                $deleteMenuIds = array_diff($oldMenuIds, $menuIds);
                // 找出需要新增的 menuIds
                $addMenuIds = array_diff($menuIds, $oldMenuIds);
                if ($deleteMenuIds) {
                    SysRoleMenuModel::query()->where('role_id', $params['id'])->whereIn('menu_id', $deleteMenuIds)->delete();
                }
                if ($addMenuIds) {
                    $save = array_map(function ($menuId) use ($params) {
                        return ['menu_id' => $menuId, 'role_id' => $params['id']];
                    }, $addMenuIds);
                    SysRoleMenuModel::insert($save);
                }
            });
            return true;
        } catch (\Exception $e) {
            exceptionLog($e);
            throw new RespBusinessException($e->getMessage());
        }
    }


    /**
     * 删除角色
     * @param array $params
     * @return true
     * @throws RespBusinessException
     */
    public static function delete(array $params): bool
    {
        try {
            // 判断当前角色是否存在用户
            $userExists = SysAdminRoleModel::checkExists((new SysAdminRoleModel), ['role_id' => $params['id']]);
            if ($userExists) {
                throw new RespBusinessException('当前角色下存在用户，无法删除');
            }
            DB::transaction(function () use ($params) {
                // 删除角色
                SysRoleModel::query()->where('id', $params['id'])->delete();
                // 删除角色菜单关联
                SysRoleMenuModel::query()->where('role_id', $params['id'])->delete();
            });
            return true;
        } catch (\Exception $e) {
            throw new RespBusinessException($e->getMessage());
        }
    }

    /**
     * 角色详情
     * @param string $id
     * @return array
     */
    public static function detail(string $id): array
    {
        $detail = SysRoleModel::find($id)->toArray();
        if (!empty($detail)) {
            $menuIds = SysRoleMenuModel::query()->where('role_id', $id)->pluck('menu_id');
        }
        $detail['menuIds'] = $menuIds ?? [];
        return $detail;
    }
}