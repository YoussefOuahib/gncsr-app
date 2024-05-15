import axios from "axios";
import { inject, ref, reactive } from "vue";
import { useRouter } from "vue-router";

const user = reactive({
    name: "",
    email: "",
});
const userInformations = ref({});
const isAdmin = ref(false);
export default function useAuth() {
    const router = useRouter();
    // const validationErrors = ref({})
    const isLoading = ref(false);

    const loginForm = reactive({
        email: "",
        password: "",
        remember: false,
    });

    // const swal = inject("$swal");

    const submitLogin = async () => {
        console.log("hello login");

        axios.get('/sanctum/csrf-cookie').then(response => {
            axios.post("/login", loginForm).then(async (response) => {
                loginUser(response);
            }).finally(() => {
                isLoading.value = false;
            }).catch((error) => console.log(error))
        });


    };

    const loginUser = async (response) => {
        user.name = response.data.name;
        user.email = response.data.email;
        isAdmin.value = response.data.is_admin;
        localStorage.setItem("loggedIn", JSON.stringify(true));
        if(isAdmin.value) {
            localStorage.setItem('isAdmin', JSON.stringify(true));
            await router.push({ path: "/customers" });

        }
        else {
            localStorage.setItem('isAdmin', JSON.stringify(false));
            await router.push({ path: "/synchronization" });

        }
    };

    const getUser = () => {
        axios.get("/api/info/user").then((response) => {
            loginUser(response);
        }).catch((error) => console.log(error));
    };
    const getUserInformations = () => {
        axios.get("/api/info/user").then((response) => {
            console.log(response.data);
            userInformations.value = response.data;
        }).catch((error) => console.log(error));
    };
    const checkIfUserIsAdmin = () => {
        axios.get("/api/info/user").then((response) => {
            console.log('hello world');
           
        }).catch((error) => console.log(error));
    }

    const logout = async () => {
        axios.post("logout").then(response => {
            localStorage.setItem('loggedIn', JSON.stringify(false))
            router.push({ path: "/login" })
        });
    };
    return {
        getUser,
        getUserInformations,
        userInformations,
        checkIfUserIsAdmin,
        user,
        loginForm,
        submitLogin,
        logout,
        isAdmin,
    };
}
