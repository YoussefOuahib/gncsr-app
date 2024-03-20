<template>
    <div>
        <v-btn class="ml-4 mt-3" color="primary" prepend-icon="mdi-connection">
            Connect

            <v-dialog v-model="dialog" activator="parent" width="1024">
                <v-card>
                    <v-form @submit.prevent="storeCredentials(credentialsForm)" enctype="multipart/form-data">
                        <v-card-title>
                        </v-card-title>
                        <v-card-text>
                            <v-container>
                                <span class="text-subtitle-1 text-medium-emphasis mt-3">TAK Credentials</span>

                                <v-row>

                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="URL" v-model="credentialsForm.tak_url" required></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Login" v-model="credentialsForm.tak_login"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Password" v-model="credentialsForm.tak_password" type="password" required></v-text-field>
                                    </v-col>
                                </v-row>
                                <span class="text-subtitle-1 text-medium-emphasis mt-3">Sharepoint Credentials</span>

                                <v-row>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="URL" required v-model="credentialsForm.sharepoint_url"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Client Id" type="password" required v-model="credentialsForm.sharepoint_client_id"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Client Secret" type="password" v-model="credentialsForm.sharepoint_client_secret" required></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Tenant Id" type="password" v-model="credentialsForm.sharepoint_tenant_id" required></v-text-field>
                                    </v-col>
                                </v-row>

                                <span class="text-subtitle-1 text-medium-emphasis mt-3">Microsoft Dynamics
                                    Credentials</span>

                                <v-row>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="URL" required v-model="credentialsForm.dynamics_url"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Client Id" type="password" required v-model="credentialsForm.dynamics_client_id"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Client Secret" type="password" required v-model="credentialsForm.dynamics_client_secret"></v-text-field>
                                    </v-col>
                                </v-row>

                            </v-container>
                            <v-btn type="submit" prepend-icon="mdi-connection" class="mb-4" color="primary" size="large" variant="tonal">
                                Connect
                            </v-btn>
                        </v-card-text>
                    </v-form>

                </v-card>

            </v-dialog>
        </v-btn>
        <v-btn class="ml-4 mt-3" @click="executeProcess" :loading="loading" color="secondary" prepend-icon="mdi-play">
            Execute
        </v-btn>
    </div>
</template>
<script>
import useCredentials from '@/Comopsables/credentials';
import { reactive, ref } from 'vue';
export default {
    name: "mission",
    data() {
        return {
            dialog: false,
        }
    },
    setup() {
        const { storeCredentials } = useCredentials();
        const loading = ref(false);

        const credentialsForm = reactive({
            tak_url: '', tak_login: '', tak_password: '',
            sharepoint_url: '', sharepoint_client_id: '', sharepoint_client_secret: '', sharepoint_tenant_id: '',
            dynamics_url: '', dynamics_client_id: '', dynamics_client_secret: '',
        })

        const executeProcess = async () => {
            loading.value = true;
            axios.post("/api/dynamics/execute").then(response => {
                console.log(response.data);

            }).catch((error) => { console.log(error) }).finally(() => {
                loading.value = false;
            });
            }

        return {storeCredentials, executeProcess ,credentialsForm, loading}

    }
}
</script>