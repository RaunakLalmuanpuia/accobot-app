import { usePage } from '@inertiajs/vue3'

export const hasPermission = (perm) => {
    const page = usePage()
    return page.props.auth.permissions.includes(perm)
}

export const hasFeature = (feature) => {
    const page = usePage()
    return (page.props.subscription?.features ?? []).includes(feature)
}
