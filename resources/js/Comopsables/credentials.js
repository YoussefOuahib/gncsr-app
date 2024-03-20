import axios from "axios";
import {  ref, reactive } from "vue";
import { useRouter } from "vue-router";

export default function useCredentials() {

    const storeCredentials = async (credentialsForm) => {

        axios.post("/api/store/credentials", credentialsForm
        ).then(response => {
           console.log('success');

        }).catch((error) => { console.log(error) });
    }
    

    return {
        storeCredentials,
    }
}