<template>
    <v-app id="inspire">
      <v-navigation-drawer v-model="drawer" app>
            <v-sheet class="pa-2 text-center">
                <div>Hi, Owner</div>

            </v-sheet>
            <v-spacer></v-spacer>

            <v-divider></v-divider>


            <v-list density="compact" nav>
                <v-list-item prepend-icon="mdi-account-tie" :to="{ name: 'missions' }" color="primary"  title="Missions" value="missions"></v-list-item>

        </v-list>
        </v-navigation-drawer>

        <v-app-bar>
            <v-app-bar-nav-icon @click="drawer = !drawer"></v-app-bar-nav-icon>

            <template v-slot:append>
                <!-- <v-btn size="small" color="primary">
                    <v-avatar class="mr-2" image="/images/recycle-bag.png" size="20" rounded="0" ></v-avatar>
                    Profile
                </v-btn> -->
      
                <v-btn @click="logout" color="primary" icon="mdi-logout"></v-btn>
        
            </template>
        </v-app-bar>

        
        <v-main>
            <router-view></router-view>
        </v-main>
    </v-app>
</template>
<script>
import { onMounted, ref } from "vue";
import useAuth from "../Comopsables/auth";



export default {
    name: "AuthenticatedLayout",
    data: () => ({
        drawer: null,
    }),
    setup() {
        const { user, logout } = useAuth();

        return {
            user,
            logout,
    
        };
    },
    computed: {
        currentPageTitle() {
            return this.$route.meta.title;
        },
    },
};
</script>