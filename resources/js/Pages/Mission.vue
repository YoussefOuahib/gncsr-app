<script>
import useCredentials from '@/Comopsables/credentials';
import { reactive, ref, onMounted } from 'vue';
import axios from 'axios';
export default {
    name: "synchronization",
    data() {
        return {
            dialog: false,
        }
    },
    setup() {
        const { storeCredentials } = useCredentials();
        const loading = ref(false);
        const credentials = ref([]);
        const statusMessage = ref('');
        const credentialsForm = reactive({
            tak_url: '', tak_login: '', tak_password: '',
            sharepoint_url: '', sharepoint_client_id: '', sharepoint_client_secret: '', sharepoint_tenant_id: '',
            dynamics_url: '', dynamics_client_id: '', dynamics_client_secret: '',
        })

        const executeProcess = async () => {
            loading.value = true;
            await axios.post("/api/dynamics/execute").then(response => {

            }).catch((error) => { console.log(error) }).finally(() => {
                loading.value = false;
            }).finally(() => {
                loading.value = false;
            });
        }
        const getCredentials = async () => {
            await axios.get('/api/get/credentials').then(response => {
                console.log('hello get credentials');
                credentialsForm.tak_url = response.data.data.tak_url;
                credentialsForm.tak_login = response.data.data.tak_login;
                credentialsForm.tak_password = response.data.data.tak_password;
                credentialsForm.sharepoint_url = response.data.data.sharepoint_url;
                credentialsForm.sharepoint_client_id = response.data.data.sharepoint_client_id;
                credentialsForm.sharepoint_client_secret = response.data.data.sharepoint_client_secret;
                credentialsForm.sharepoint_tenant_id = response.data.data.sharepoint_tenant_id;
                credentialsForm.dynamics_client_id = response.data.data.dynamics_client_id;
                credentialsForm.dynamics_url = response.data.data.dynamics_url;
                credentialsForm.dynamics_client_secret = response.data.data.dynamics_client_secret;
            }).catch(error => {
                console.log(error);
            })
        }

        onMounted(() => {
            getCredentials();
            window.Pusher.logToConsole = true;

            const pusher = new Pusher(import.meta.env.VITE_PUSHER_APP_KEY, {
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
                encrypted: true,
            });

            const channel = pusher.subscribe('response-channel');

            channel.bind('response.received', (data) => {
                statusMessage.value = data.message;
            });
        });
        return { storeCredentials, getCredentials, executeProcess, credentialsForm, loading, statusMessage }
    }
}
</script>
<template>
    <div>
        <v-btn class="ml-4 mt-3" color="primary" prepend-icon="mdi-connection">
            Connect

            <v-dialog v-model="dialog" activator="parent" width="1024">
                <v-card>
                    <v-form @submit.prevent="storeCredentials(credentialsForm)" enctype="multipart/form-data">
                        <v-card-text>
                            <v-container>
                                <span class="text-subtitle-1 text-medium-emphasis mt-3">TAK Credentials</span>

                                <v-row>

                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="URL" v-model="credentialsForm.tak_url"
                                            required></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Login" v-model="credentialsForm.tak_login"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Password" v-model="credentialsForm.tak_password"
                                            type="text" required></v-text-field>
                                    </v-col>
                                </v-row>
                                <span class="text-subtitle-1 text-medium-emphasis mt-3">Sharepoint Credentials</span>

                                <v-row>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="URL" required
                                            v-model="credentialsForm.sharepoint_url"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Client Id" type="text" required
                                            v-model="credentialsForm.sharepoint_client_id"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Client Secret" type="text"
                                            v-model="credentialsForm.sharepoint_client_secret" required></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="3">
                                        <v-text-field label="Tenant Id" type="text"
                                            v-model="credentialsForm.sharepoint_tenant_id" required></v-text-field>
                                    </v-col>
                                </v-row>

                                <span class="text-subtitle-1 text-medium-emphasis mt-3">Microsoft Dynamics
                                    Credentials</span>

                                <v-row>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="URL" required
                                            v-model="credentialsForm.dynamics_url"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Client Id" type="text" required
                                            v-model="credentialsForm.dynamics_client_id"></v-text-field>
                                    </v-col>
                                    <v-col cols="12" sm="12" md="4">
                                        <v-text-field label="Client Secret" type="text" required
                                            v-model="credentialsForm.dynamics_client_secret"></v-text-field>
                                    </v-col>
                                </v-row>

                            </v-container>

                            <v-btn type="submit" prepend-icon="mdi-connection" class="mb-4" color="primary" size="large"
                                variant="tonal">
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
        <v-container style="height: 400px;" v-if="loading">
            <v-row align-content="center" class="fill-height" justify="center">
                <v-col class="text-subtitle-1 text-center" cols="12">
                    {{ statusMessage }}
                </v-col>
                <v-col cols="6">
                    <v-progress-linear color="deep-purple-accent-4" height="6" indeterminate
                        rounded></v-progress-linear>
                </v-col>
            </v-row>
        </v-container>

    </div>
</template>
