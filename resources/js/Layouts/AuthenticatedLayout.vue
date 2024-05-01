<script setup>
import { onMounted, ref, computed } from "vue";
import useAuth from "../Comopsables/auth";
import InputError from '../Components/InputError.vue';
import InputLabel from '../Components/InputLabel.vue';
import PrimaryButton from '../Components/PrimaryButton.vue';
import TextInput from '../Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';

const drawer = ref(null);
const resetDialog = ref(false);
const { user, logout, isAdmin, checkIfUserIsAdmin } = useAuth();
onMounted(() => {
    checkIfUserIsAdmin();
})
const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    axios.put(route('password.update'), {}, {
    params: {
        preserveScroll: true,
    },
})
.then(() => {
    form.reset();
})
.catch(error => {
    if (error.response) {
        const { data } = error.response;
        if (data.errors.password) {
            form.reset('password', 'password_confirmation');
            passwordInput.value.focus();
        }
        if (data.errors.current_password) {
            form.reset('current_password');
            currentPasswordInput.value.focus();
        }
    } else {
        // Handle other types of errors
    }
});
};
</script>
<template>
    <v-app id="inspire">
        <v-navigation-drawer v-model="drawer" app>
            <v-sheet class="pa-2 text-center">
                <div>Hi, User</div>
            </v-sheet>
            <v-spacer></v-spacer>
            <v-divider></v-divider>
            <v-list density="compact" nav>
                <v-list-item prepend-icon="mdi-file-arrow-up-down" :to="{ name: 'synchronization' }" color="primary"
                    title="Synchronization" value="synchronization"></v-list-item>
                <v-list-item prepend-icon="mdi-account-tie" v-if="isAdmin" :to="{ name: 'customers' }" color="primary"
                    title="Customers" value="customers"></v-list-item>
            </v-list>
        </v-navigation-drawer>

        <v-app-bar>
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>
            <template v-slot:append>
                <v-btn color="primary" @click="resetDialog = true" icon="mdi-lock-reset"></v-btn>
                <v-btn @click="logout" color="primary" icon="mdi-logout"></v-btn>
            </template>
        </v-app-bar>
        <v-dialog v-model="resetDialog" width="1024">
            <v-card>

                <form @submit.prevent="updatePassword" class="mt-6 space-y-6">
                    <v-container>
                        <v-card-text>
                            <span class="text-subtitle-1 text-medium-emphasis mt-3">Update Your password</span>

                            <v-row class="mt-2">
                                <v-col cols="12" sm="4" md="4">
                                    <v-text-field label="Current Password" type="password" v-model="form.current_password">
                                    </v-text-field>

                                    <InputError :message="form.errors.current_password" class="mt-2" />
                                </v-col>

                                <v-col cols="12" sm="4" md="4">

                                    <v-text-field label="New Password" type="password" v-model="form.password">
                                    </v-text-field>

                                    <InputError :message="form.errors.password" class="mt-2" />
                                </v-col>

                                <v-col cols="12" sm="4" md="4">

                                    <v-text-field label="Confirm Password" type="password"
                                        v-model="form.password_confirmation">
                                    </v-text-field>
                                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                                </v-col>
                            </v-row>
                        </v-card-text>
                        <v-card-actions class="justify-end">
                            <v-btn color="secondary" @click="resetDialog = false">Close</v-btn>
                            <v-btn color="primary" type="submit" @click="resetDialog = false" :disabled="form.processing">Save</v-btn>


                        </v-card-actions>
                    </v-container>

                </form>


            </v-card>

        </v-dialog>
        <v-main>
            <router-view></router-view>
        </v-main>
    </v-app>
</template>
