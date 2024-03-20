import axios from "axios";
import { inject, ref, reactive } from "vue";
import { useRouter } from "vue-router";

const user = reactive({
    name: "",
    email: "",
});
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



        await router.push({ name: "missions" });
    };

    const getUser = () => {
        axios.get("/api/user")
            .then((response) => {
                loginUser(response)
            }).catch((error) => console.log(error));
    };


    const logout = async () => {
        
        axios.post("logout").then(response => {
            localStorage.setItem('loggedIn', JSON.stringify(false))
            router.push({ name: "home" })


        });
    };
   
   

    return {
       
        getUser,
        user,
        loginForm,
        submitLogin,
        logout,
        
    };
}
