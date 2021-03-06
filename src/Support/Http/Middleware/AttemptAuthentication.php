<?php

namespace Nuwave\Lighthouse\Support\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class AttemptAuthentication
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string[]  ...$guards
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->attemptAuthentication($guards);

        return $next($request);
    }

    /**
     * Attempt to authenticate the user, but don't do anything if they are not.
     *
     * @return void
     */
    protected function attemptAuthentication(array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return $this->auth->shouldUse($guard);
            }
        }
    }
}
