<?php

namespace Crastlin\LaravelAnnotation\Middleware;

use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Redis;

class SyncLockMiddleware
{

    function handle(\Illuminate\Http\Request $request, \Closure $next)
    {
        $path = $request->path();
        $redis = null;
        $key = '';
        if ($path && preg_match('~^[\w\-/]+$~', $path)) {
            $config = config('annotation');
            $filePath = !empty($config['annotation_path']) ? rtrim($config['annotation_path'], '/') : 'data';
            $routeBasePath = base_path($filePath . '/routes');
            $file = "{$routeBasePath}/lock_annotation.php";
            $map = is_file($file) ? require_once $file : [];
            $annotation = !empty($map) && array_key_exists($path, $map) ? $map[$path] : [];
            if (!empty($annotation)) {
                $key = "{$annotation['prefix']}{$annotation['name']}";
                if (!empty($annotation['suffix']) || !empty($annotation['suffixes'])) {
                    $annotation['suffixes'] = $annotation['suffixes'] ?? [];
                    if (!empty($annotation['suffix']))
                        $annotation['suffixes'][] = $annotation['suffix'];
                    $suffixKey = '';
                    foreach ($annotation['suffixes'] as $suffix):
                        if (substr($suffix, 0, 1) == '$') {
                            $value = $request->input(substr($suffix, 1));
                            $suffixKey .= is_string($value) ? $value : serialize($value);
                        } else {
                            $suffixKey .= ltrim($annotation['suffix'], ':');
                        }
                    endforeach;
                    $key .= ':' . md5($suffixKey);
                }
                $redis = Redis::connection();
                if (!$redis->set($key, 1, 'ex', $annotation['expire'] ?? 86400, 'nx'))
                    return response()->json($annotation['respone'] ?? ['code' => $annotation['code'] ?? 500, 'msg' => $annotation['msg'] ?? 'Request busy, please try again later'])->header('Pragma', 'no-cache')
                        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            }
        }
        $response = $next($request);
        if ($redis && empty($annotation['once']))
            $redis->del($key);
        return $response;
    }
}