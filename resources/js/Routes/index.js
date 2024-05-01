import { createWebHistory, createRouter } from "vue-router";
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout.vue'
import GuestLayout from '../Layouts/GuestLayout.vue'
import Login from '../Pages/Auth/Login.vue'
import Register from '../Pages/Auth/Register.vue'
import Home from '../Pages/Home.vue'
import Mission from "../Pages/Mission.vue"
import Customer from "../Pages/Customer.vue"
import Profile from "../Pages/Profile/Edit.vue"

function auth(to, from, next) {

    if (JSON.parse(localStorage.getItem('loggedIn'))) {
        next()
    }
    if (from.path == '/' && !JSON.parse(localStorage.getItem('loggedIn'))) {
        next('login')
    }
    next('/login')

}

const routes = [
    {
        component: GuestLayout,
        path: '/',
        redirect: { name: 'synchronization' },
        children: [
         
            {
                path: '/login',
                name: 'login',
                component: Login
            },
            {
                path: '/register',
                name: 'register',
                component: Register
            },
        ]
    },
    {

        component: AuthenticatedLayout,
        beforeEnter: auth,
        children: [
            {
                path: '/synchronization',
                name: 'synchronization',
                component: Mission
            },
            {
                path: '/customers',
                name: 'customers',
                component: Customer
            },
            
        ]
    },


]

export default createRouter({
    history: createWebHistory(),
    routes
})