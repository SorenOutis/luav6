// eslint-disable-next-line @typescript-eslint/no-unused-vars
export type RouteDefinition<TMethod = any> = any;

const stub: any = new Proxy(() => '', {
    get: () => stub,
    apply: () => '',
});

export default stub;
export const login: any = stub;
export const register: any = stub;
export const logout: any = stub;
export const dashboard: any = stub;
export const password: any = stub;
export const verification: any = stub;
export const twoFactor: any = stub;
export const profile: any = stub;
export const appearance: any = stub;
export const assignments: any = stub;
export const exams: any = stub;
export const chats: any = stub;
export const ngl: any = stub;
export const userPassword: any = stub;
export const show: any = stub;
export const index: any = stub;
export const grades: any = stub;
export const store: any = stub;
export const update: any = stub;
export const edit: any = stub;
export const destroy: any = stub;
export const create: any = stub;
export const email: any = stub;
export const send: any = stub;
export const home: any = stub;
export const request: any = stub;
export const confirm: any = stub;
export const enable: any = stub;
export const disable: any = stub;
export const qrCode: any = stub;
export const secretKey: any = stub;
export const recoveryCodes: any = stub;
export const regenerateRecoveryCodes: any = stub;
export const message: any = stub;
export const stream: any = stub;
export const like: any = stub;
