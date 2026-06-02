<?php

namespace app\media\middleware;

use Closure;
use think\facade\Session;
use think\facade\View;

class Csrf
{
    public function handle($request, Closure $next)
    {
        $token = Session::get('csrf_token');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('csrf_token', $token);
        }

        View::assign('csrfToken', $token);

        if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $user = Session::get('r_user');
            if (!$user) {
                return $next($request);
            }

            $requestToken = $request->header('X-CSRF-Token')
                ?: $request->post('__csrf_token__')
                ?: $request->param('__csrf_token__');

            if (!$requestToken || !hash_equals($token, $requestToken)) {
                if ($request->isAjax() || $request->isJson()) {
                    return json(['code' => 403, 'message' => 'CSRF token validation failed'], 403);
                }
                return response('CSRF token validation failed', 403);
            }
        }

        return $next($request);
    }
}
