import { createApp, onMounted } from 'vue/dist/vue.esm-bundler.js'
import router from './Routes/index.js'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import './guard'
import './bootstrap';
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import useAuth from "./Comopsables/auth.js";




const app = createApp({
  setup() {
    const { getUser } = useAuth()
    if (JSON.parse(localStorage.getItem('loggedIn'))) {
      onMounted(getUser)
    }
  }
});

const vuetify = createVuetify({
    components: {
    ...components,    
  },
 
})

app.use(router);
app.use(vuetify).mount("#app");
