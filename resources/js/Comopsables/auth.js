import axios from "axios";
import { inject, ref, reactive } from "vue";
import { useRouter } from "vue-router";

const user = reactive({
    name: "",
    email: "",
});
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
        localStorage.setItem("loggedIn", JSON.stringify(true));
        await router.push({ name: "syncronization" });
    };

    const getUser = () => {
        axios.get("/api/info/user").then((response) => {
            loginUser(response);
        }).catch((error) => console.log(error));
    };
    const checkIfUserIsAdmin = () => {
        axios.get("/api/info/user").then((response) => {
            console.log('hello world');
            isAdmin.value = response.data.is_admin;
            console.log(response.data.is_admin);

        }).catch((error) => console.log(error));
    }

    const logout = async () => {
        axios.post("logout").then(response => {
            localStorage.setItem('loggedIn', JSON.stringify(false))
            router.push({ name: "home" })
        });
    };
    return {
        getUser,
        checkIfUserIsAdmin,
        user,
        loginForm,
        submitLogin,
        logout,
        isAdmin,
    };
}
