<script setup>
import { ref, onMounted, reactive } from "vue";
import useCustomers from '@/Comopsables/customers';
import axios from "axios";
const dialog = ref(false);
const snackbar = ref(false);
const loading = ref(false);
const editUserCredentialsDialog = ref(false);
const credentialsDialog = ref(false);
const selectedUser = ref();
const statusMessage = ref('');
const credentialsForm = ref({
  tak_url: '', tak_login: '', tak_password: '',
  sharepoint_url: '', sharepoint_client_id: '', sharepoint_client_secret: '', sharepoint_tenant_id: '',
  dynamics_url: '', dynamics_client_id: '', dynamics_client_secret: '',
});
const customerForm = ref({
  full_name: '', email: '', password: '',
})
const newCustomerForm = ref({
  full_name: '', email: '', password: '', is_admin: false,
})
const { getCustomers, addNewCustomer, updateCustomerCredentials, updateConnectionCredentials, customers, deleteCustomer } = useCustomers();
const editUserCredentials = (userId) => {
  selectedUser.value = userId;
  Object.keys(customerForm.value).forEach((key) => {
    customerForm.value[key] = '';
  });
  editUserCredentialsDialog.value = true;
  showCredentials(userId);
}

const getCredentials = async (userId) => {
  selectedUser.value = userId;
  Object.keys(credentialsForm.value).forEach((key) => {
    credentialsForm.value[key] = '';
  });
  credentialsDialog.value = true;
  showConnectionInformations(userId);
}
const showCredentials = async (userId) => {
  axios.get("/api/customers/" + userId).then(response => {
    if (response.data) {
      customerForm.value = response.data.user;
    }
  }).catch((error) => {
    console.log(error);
  });
}
const synchronization = async (userId) => {
  loading.value = true;
  axios.get("/api/execute/" + userId).catch((error) => {
    console.log(error);
  }).finally(() => {
    loading.value = false;
  })
}

const showConnectionInformations = async (userId) => {
  await axios.get("/api/show/credentials/" + userId)
    .then(response => {
      if (response.data.credential) {
        credentialsForm.value = response.data.credential;
      }
    }).catch((error) => {
      console.log(error);
    });
}
onMounted(() => {
  getCustomers();
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

</script>
<template>
  <div>
    <v-btn class="ml-4 mt-3" color="primary" @click="emptyCustomerForm" prepend-icon="mdi-account-plus">
      Add new customer

      <v-dialog v-model="dialog" activator="parent" width="1024">
        <v-card>
          <v-form @submit.prevent="addNewCustomer(newCustomerForm)" enctype="multipart/form-data">
            <v-container>
              <v-card-text>
                <span class="text-subtitle-1 text-medium-emphasis mt-3">User Credentials</span>
                <v-row>
                  <v-col cols="12" sm="4" md="4">
                    <v-text-field label="Full Name" v-model="newCustomerForm.name" required></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="4" md="4">
                    <v-text-field label="Email" v-model="newCustomerForm.email"></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="4" md="4">
                    <v-text-field label="Password" v-model="newCustomerForm.password" type="text"
                      required></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="12" md="12">
                    <v-switch label="Is Admin ?" color="primary" v-model="newCustomerForm.is_admin"
                      hide-details></v-switch>
                  </v-col>

                </v-row>

              </v-card-text>
              <v-card-actions class="justify-end">
                <v-btn variant="plain" @click="dialog = false" class="p-3">
                  Close
                </v-btn>
                <v-btn type="submit" class="p-3" @click="dialog = false" color="primary">
                  Save
                </v-btn>
              </v-card-actions>
            </v-container>

          </v-form>

        </v-card>

      </v-dialog>
    </v-btn>
    <v-table>
      <thead>
        <tr>
          <th class="text-left">
            Name
          </th>
          <th class="text-left">
            Email
          </th>
          <th class="text-left">
            Is admin ?
          </th>
          <th class="text-left">
            Actions
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in customers" :key="item.name">

          <td>{{ item.name }}</td>
          <td>{{ item.email }}</td>
          <td> {{ item.is_admin ? 'Yes' : 'No' }}</td>
          <td>
            <v-btn class="ml-3" @click="getCredentials(item.id)" color="green-lighten-2" icon="mdi-wan"
              size="x-small"></v-btn>
            <v-btn class="ml-3" color="red-darken-2" icon="mdi-trash-can" @click="deleteCustomer(item.id)"
              size="x-small"></v-btn>
            <v-btn class="ml-3" color="blue-lighten-2" @click="editUserCredentials(item.id)" icon="mdi-pencil-outline"
              size="x-small"></v-btn>
            <v-btn class="ml-3" color="indigo-lighten-2" v-if="item.has_connection" @click="synchronization(item.id)"
              icon="mdi-sync" size="x-small"></v-btn>
            <v-btn class="ml-3" color="indigo-lighten-3" v-else @click="snackbar = true" icon="mdi-sync"
              size="x-small"></v-btn>
            <v-snackbar v-model="snackbar">
              No connection information is setup for this client. Please add and try again.
              <template v-slot:actions>
                <v-btn color="pink" variant="text" @click="snackbar = false">
                  Close
                </v-btn>
              </template>
            </v-snackbar>

          </td>
        </tr>
      </tbody>
    </v-table>
    <v-dialog v-model="editUserCredentialsDialog" width="1024">
      <v-card>
        <v-form @submit.prevent="updateCustomerCredentials(customerForm, selectedUser)" enctype="multipart/form-data">
          <v-container>
            <v-card-text>
              <span class="text-subtitle-1 text-medium-emphasis mt-3">User Credentials</span>

              <v-row class="mt-2">
                <v-col cols="12" sm="4" md="4">
                  <v-text-field label="Full Name" v-model="customerForm.name" required></v-text-field>
                </v-col>
                <v-col cols="12" sm="4" md="4">
                  <v-text-field label="Email" v-model="customerForm.email"></v-text-field>
                </v-col>
                <v-col cols="12" sm="4" md="4">
                  <v-text-field label="Password" v-model="customerForm.password" type="text" required></v-text-field>
                </v-col>

              </v-row>

            </v-card-text>
            <v-card-actions class="justify-end">
              <v-btn variant="plain" @click="editUserCredentialsDialog = false" class="p-3">
                Close
              </v-btn>
              <v-btn type="submit" class="p-3" @click="editUserCredentialsDialog = false" color="primary">
                Update
              </v-btn>
            </v-card-actions>
          </v-container>

        </v-form>

      </v-card>

    </v-dialog>
    <v-dialog v-model="credentialsDialog">
      <v-card prepend-icon="mdi-connection" title="Connect">
        <v-form @submit.prevent="updateConnectionCredentials(credentialsForm, selectedUser)"
          enctype="multipart/form-data">
          <v-card-text>
            <div class="mx-8 my-4">
              <span class="text-subtitle-1 text-medium-emphasis mt-3">TAK Credentials</span>
              <v-row>
                <v-col cols="12" sm="12" md="4">
                  <v-text-field label="URL" v-model="credentialsForm.tak_url" required></v-text-field>
                </v-col>
                <v-col cols="12" sm="12" md="4">
                  <v-text-field label="Login" v-model="credentialsForm.tak_login"></v-text-field>
                </v-col>
                <v-col cols="12" sm="12" md="4">
                  <v-text-field label="Password" v-model="credentialsForm.tak_password" type="text"
                    required></v-text-field>
                </v-col>
              </v-row>
              <span class="text-subtitle-1 text-medium-emphasis mt-3">Sharepoint Credentials</span>

              <v-row>
                <v-col cols="12" sm="12" md="3">
                  <v-text-field label="URL" required v-model="credentialsForm.sharepoint_url"></v-text-field>
                </v-col>
                <v-col cols="12" sm="12" md="3">
                  <v-text-field label="Client Id" type="text" required
                    v-model="credentialsForm.sharepoint_client_id"></v-text-field>
                </v-col>
                <v-col cols="12" sm="12" md="3">
                  <v-text-field label="Client Secret" type="text" v-model="credentialsForm.sharepoint_client_secret"
                    required></v-text-field>
                </v-col>
                <v-col cols="12" sm="12" md="3">
                  <v-text-field label="Tenant Id" type="text" v-model="credentialsForm.sharepoint_tenant_id"
                    required></v-text-field>
                </v-col>
              </v-row>

              <span class="text-subtitle-1 text-medium-emphasis mt-3">Microsoft Dynamics
                Credentials</span>

              <v-row>
                <v-col cols="12" sm="12" md="4">
                  <v-text-field label="URL" required v-model="credentialsForm.dynamics_url"></v-text-field>
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
            </div>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn text="Close" variant="plain" @click="credentialsDialog = false"></v-btn>
            <v-btn color="primary" text="Save" variant="plain" type="submit" @click="credentialsDialog = false"></v-btn>
          </v-card-actions>
        </v-form>
      </v-card>
    </v-dialog>
    <v-dialog width="auto" v-model="loading">
      <v-card
        max-width="400"
        prepend-icon="mdi-update"
        :text="statusMessage"
        title="Running.."
      >
     
      </v-card>
    </v-dialog>
  </div>
</template>
