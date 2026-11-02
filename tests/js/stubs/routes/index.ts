export const dashboard = () => ({ url: '/dashboard' });
export const logout = () => ({ url: '/logout', method: 'post' });

export const login = (options?: { query?: Record<string, string> }) => {
    const qs = options?.query?.app ? `?app=${options.query.app}` : '';
    return { url: `/login${qs}`, method: 'get' };
};
login.url = (options?: { query?: Record<string, string> }) => {
    const qs = options?.query?.app ? `?app=${options.query.app}` : '';
    return `/login${qs}`;
};

export const register = (options?: { query?: Record<string, string> }) => {
    const qs = options?.query?.app ? `?app=${options.query.app}` : '';
    return { url: `/register${qs}`, method: 'get' };
};
register.url = (options?: { query?: Record<string, string> }) => {
    const qs = options?.query?.app ? `?app=${options.query.app}` : '';
    return `/register${qs}`;
};

export default { dashboard, logout, login, register };
