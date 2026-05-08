<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchInput from '@/Components/SearchInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputSelect from '@/Components/InputSelect.vue';
import BadgeYellow from '@/Components/BadgeYellow.vue';
import BadgeGreen from '@/Components/BadgeGreen.vue';
import AnimateSpin from '@/Components/Icons/AnimateSpin.vue';
import Pagination from '@/Components/Paginator.vue';
import ChevronRightIcon from '@/Components/Icons/ChevronRightIcon.vue';

const props = defineProps({
    title: String,
    employees: Array,
    general_direction: Array,
    directions: Array,
    subdirectorate: Array,
    showPaginator: Boolean,
    filters: Object,
    paginator: {
        type: Object,
        default : {
            from: 0,
            to: 0,
            total: 0,
            pages: []
        }
    }
});

const toast = useToast();

const form = useForm({
    search: "",
    gd: 0,
    d: 0,
    sd: 0,
    page: 1
});

const loading = ref(false);
const debouncedSearch = ref(null);

onMounted(()=>{
    form.gd = props.filters.gd ?? 0;
    form.d = props.filters.d ?? 0;
    form.sd = props.filters.sd ?? 0;
    form.p = props.filters.page ?? 1;
    form.search = props.filters.search ?? undefined;
});

onUnmounted(() => {
    // Clear any pending debounced search
    if (debouncedSearch.value) {
        clearTimeout(debouncedSearch.value);
    }
});

function handleInputSearch(search){
    // Clear previous debounced call
    if (debouncedSearch.value) {
        clearTimeout(debouncedSearch.value);
    }
    
    // Set new debounced call
    debouncedSearch.value = setTimeout(() => {
        form.search = search;
        form.p = 1;
        reloadData();
    }, 800);
}

function reloadData(){
    loading.value = true;
    
    // * prepare the query params
    var params = [];
    if(form.gd){
        params.push(`gd=${form.gd}`);
    }
    
    if(form.d && form.d > 0){
        params.push(`d=${form.d}`);
    }
    
    if(form.sd && form.sd > 0){
        params.push(`sd=${form.sd}`);
    }

    if(form.page && form.page > 1){
        params.push(`p=${form.page}`);
    }
    
    if(form.search){
        params.push(`se=${form.search}`);
    }

    // * reload the view
    router.visit("?" + params.join("&"), {
        method: 'get',
        only: ['employees', 'directions', 'subdirectorate', 'showPaginator', 'paginator'],
        preserveState: true,
        onError:(err)=>{
            toast.error("Error al obtener los datos");
        },
        onSuccess: ()=>{
            loading.value = false;
        }
    });
}

function handleGeneralDirectionSelect(){
    form.d = 0;
    form.sd = 0;
    form.page = 1;
    reloadData();
}

function handleDirectionSelect(){
    form.sd = 0;
    form.page = 1;
    reloadData();
}

function handleSubDirectionSelect(){
    form.page = 1;
    reloadData();
}

function changePage(pageNumber){
    form.page = pageNumber;
    reloadData();
}

</script>

<template>

    <Head title="Administrador" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Empleados</h2>
        </template>

        <div class="px-4 py-4 rounded-lg min-h-screen max-w-screen-xl mx-auto">
            
            <!-- filter data area -->
            <div class="grid grid-cols-3 gap-2 p-4 bg-white dark:bg-gray-700 dark:border-gray-500">

                <div role="form-group" class="flex flex-col">
                    <InputLabel value="Direccion General" for="gd" />
                    <InputSelect 
                        id="gd" 
                        v-model="form.gd" 
                        v-on:change="handleGeneralDirectionSelect" 
                        :disabled="$page.props.auth.user.level_id > 2" 
                    >
                        <option selected value="0">Todos</option>
                        <option v-for="item in general_direction" :key="item.id" :value="item.id" > {{item.name }}</option>
                    </InputSelect>
                </div>

                <div role="form-group" class="flex flex-col">
                    <InputLabel value="Direccion" for="d"/>
                    <InputSelect id="d" v-model="form.d" v-on:change="handleDirectionSelect" :disabled="$page.props.auth.user.level_id > 2">
                        <option selected value="0">Todos</option>
                        <option v-for="item in directions" :key="item.id" :value="item.id" > {{item.name }}</option>
                    </InputSelect>
                </div>

                <div role="form-group" class="flex flex-col">
                    <InputLabel value="Sub direccion" for="sd" />
                    <InputSelect id="sd" v-model="form.sd" v-on:change="handleSubDirectionSelect">
                        <option value="0">Todos</option>
                        <option v-for="item in subdirectorate" :key="item.id" :value="item.id" > {{item.name }}</option>
                    </InputSelect>
                </div>

                <SearchInput class="col-span-3" placeHolder="Nombre, curp, numero de empleado" :initialValue="form.search" v-on:search="handleInputSearch"/>

            </div>

            <!-- paginator -->
            <!-- <Pagination v-if="showPaginator"
                :paginator="paginator"
                :currentPage="form.page"
                v-on:changePage="changePage"
            /> -->

            <!-- data table -->
            <table class="table w-full text-sm text-left mt-2 text-gray-500 dark:text-gray-400 dark:border-gray-500">
                <thead class="sticky top-0 z-20 text-sm uppercase text-gray-700 border bg-gradient-to-b from-gray-50 to-slate-100 dark:from-gray-800 dark:to-gray-700 dark:text-gray-200 dark:border-gray-500">
                    <AnimateSpin v-show="loading" class="w-6 h-6 text-blue-500 absolute top-3 right-2" />
                    <tr>
                        <th>#</th>
                        <th scope="col" class="px-6 py-3">
                            Nombre
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Unidad
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Estatus
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Horario
                        </th>
                        <th scope="col" class="px-6 py-3">
                            <span class="sr-only">
                                Acciones
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody id="table-body" class="bg-white dark:bg-gray-800 dark:border-gray-500">
                    <template v-if="employees && employees.length > 0">
                        <tr 
                            v-for="(employee, index) in employees" 
                            :key="employee.id" 
                            class="border-b hover:bg-gray-200"
                        >
                            <td class="pl-2">{{ index + 1 }}</td>
                            <td class="px-2 py-4">
                                <div class="flex gap-2 items-center">
                                    <img :src="employee.photo" class="h-12 w-12 rounded-md object-cover" alt="user"/>

                                    <div class="flex flex-col items-start">
                                        <p class="text-base text-gray-600 truncate">{{ employee.name }}</p>
                                        <p class="text-gray-500"># {{ employee.employeeNumber}}</p>
                                        <p class="text-xs text-gray-500">{{ employee.curp}}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="p-2">
                                <div class="text-sm text-gray-900">{{ employee.abbreviation }} </div>
                            <div class="text-xs text-gray-500">{{ employee.direction }}</div>
                            </td>

                            <td class="p-2 text-center">
                                <BadgeGreen v-if="employee.checa == 1" text="Reporta incidencias" />
                                <BadgeYellow v-else text="No reporta incidencias" class="mx-auto" />
                            </td>

                            <td class="p-2 text-center">
                                <div class="text-sm text-gray-900">{{ employee.days }} </div>
                            <div class="text-sm text-gray-500">{{ employee.horario }}</div>
                            </td>

                            <td class="p-2 text-center">
                                <Link :href=" route('employees.show', employee.employeeNumber)"
                                    class="flex items-center justify-center gap-2 border p-2 text-blue-600 transition hover:bg-blue-700 hover:text-white dark:text-blue-400 dark:hover:text-blue-300">
                                    <span>Asistencia</span>
                                    <ChevronRightIcon class="w-4 h-4" />
                                </Link>
                            </td>
                        </tr>
                    </template>
                    <template v-else>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center font-medium whitespace-nowrap dark:text-white">
                                No hay registros de Empleados.
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- paginator -->
            <Pagination v-if="showPaginator"
                :paginator="paginator"
                :currentPage="form.page"
                v-on:changePage="changePage"
            />

        </div>

    </AuthenticatedLayout>
</template>
