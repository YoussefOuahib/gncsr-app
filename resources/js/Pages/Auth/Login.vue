<template>
    <v-app>
      <form @submit.prevent="submitLogin">
        <div class="py-4">
  
          <v-card class="mx-auto pa-12 pb-8" elevation="8" max-width="500" rounded="lg">
            <v-img class="mx-auto mb-10" max-width="100"
              src="https://tailwindui.com/img/logos/mark.svg?color=indigo&shade=600"></v-img>
  
  
            <div class="text-subtitle-1 text-medium-emphasis">Email</div>
  
            <v-text-field flat center-affix color="primary" density="compact" rounded placeholder="Email address" prepend-inner-icon="mdi-email-outline"
              variant="outlined" v-model="loginForm.email"></v-text-field>
  
            <div class="text-subtitle-1 text-medium-emphasis d-flex align-center justify-space-between">
              Password
  
            </div>
  
            <v-text-field rounded flat :append-inner-icon="visible ? 'mdi-eye-off' : 'mdi-eye'" :type="visible ? 'text' : 'password'"
              color="primary" v-model="loginForm.password" placeholder="Mot de passe" prepend-inner-icon="mdi-lock-outline"
              variant="outlined" density="compact" @click:append-inner="visible = !visible"></v-text-field>
  
            <v-card color="surface-variant" variant="tonal">
  
            </v-card>
  
  
            <v-btn block class="mb-4" color="primary" size="large" type="submit" variant="tonal">
              Log In
            </v-btn>
      
          </v-card>
  
        </div>
  
      </form>
      <v-dialog v-model="isLoading" persistent width="auto">
        <v-card color="primary">
          <v-card-text>
            Veuillez patienter <v-progress-linear indeterminate color="white" class="mb-0"></v-progress-linear>
          </v-card-text>
        </v-card>
      </v-dialog>
    </v-app>
  </template>
  <script>
  import { onMounted } from "vue";
  import useAuth from "../../Comopsables/auth";
  import { useRouter } from "vue-router";
  
  
  
  export default {
    data: () => ({
      visible: false,
    }),
    setup() {
      const { loginForm, submitLogin, isLoading } = useAuth();
      const router = useRouter();
  
      onMounted(() => {
        if (JSON.parse(localStorage.getItem('loggedIn'))) {
          router.push({ name: "home" });
  
        }
      });
  
  
      return { loginForm, submitLogin };
    },
  };
  </script>
  