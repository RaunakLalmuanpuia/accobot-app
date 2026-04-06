import { usePage } from '@inertiajs/vue3'

export const hasPermission = (perm) => {
    const page = usePage()
    return page.props.auth.permissions.includes(perm)
}
