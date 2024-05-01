import axios from "axios";
import {  ref, reactive } from "vue";
import { useRouter } from "vue-router";

export default function useCredentials() {
    const userCredentials = ref({
        tak_url: '', tak_login: '', tak_password: '',
        sharepoint_url: '', sharepoint_client_id: '', sharepoint_client_secret: '', sharepoint_tenant_id: '',
        dynamics_url: '', dynamics_client_id: '', dynamics_client_secret: '',
      })
    const storeCredentials = async (credentialsForm) => {
        axios.post("/api/store/credentials", credentialsForm
        ).catch((error) => { console.log(error) });
    }
    const showCredentials = async (userId) => {
        axios.get("/api/show/credentials/" + userId)
        .then(response => {  
            userCredentials.value = response.data.credential;
            console.log('hello creds');
            console.log(userCredentials.value);
        }).catch((error) => {
            console.log(error);
        })
    }
    

    return {
        showCredentials,
        userCredentials,
        storeCredentials,
    }
}