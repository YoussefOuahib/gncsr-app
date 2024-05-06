import { createWebHistory, createRouter } from "vue-router";
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout.vue'
import GuestLayout from '../Layouts/GuestLayout.vue'
import Login from '../Pages/Auth/Login.vue'
import Register from '../Pages/Auth/Register.vue'
import Home from '../Pages/Home.vue'
import Mission from "../Pages/Mission.vue"
import Customer from "../Pages/Customer.vue"
import Profile from "../Pages/Profile/Edit.vue"
import useAuth from "@/Comopsables/auth";
import { onMounted } from "vue";

function auth(to, from, next) {
    const loggedIn = JSON.parse(localStorage.getItem('loggedIn'));
    const isAdmin = JSON.parse(localStorage.getItem('isAdmin'));

    if (loggedIn) {
        if (to.path === '/' && isAdmin) {
            next('/customers'); // Redirect admin from root to /customers
        }
        
        if (to.name === 'synchronization' && isAdmin) {
            next('/customers'); 
        } else {
            next();
        }
    } else {
        next('/login'); // Redirect to login if not logged in
    }
}

const routes = [
    {
        component: GuestLayout,
        path: '/',
        redirect: { name: 'login' },
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