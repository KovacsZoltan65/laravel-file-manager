import{v as c,c as w,w as d,o as _,a,u as s,G as g,b as t,d as u,t as p,h as v,n as V,e as b}from"./app-ddfa9cd8.js";import{_ as y}from"./GuestLayout-3537a8f8.js";import{_ as m,a as n,b as i}from"./TextInput-65742199.js";import{P as $}from"./PrimaryButton-683c0c78.js";import"./ApplicationLogo-05e4da24.js";import"./_plugin-vue_export-helper-c27b6911.js";const k=["onSubmit"],h={class:"mt-4"},q={class:"mt-4"},B={class:"mt-4"},U={class:"flex items-center justify-end mt-4"},D={__name:"Register",setup(N){const e=c({name:"",email:"",password:"",password_confirmation:""}),f=()=>{e.post(route("register"),{onFinish:()=>e.reset("password","password_confirmation")})};return(o,l)=>(_(),w(y,null,{default:d(()=>[a(s(g),{title:o.$t("register")},null,8,["title"]),t("form",{onSubmit:b(f,["prevent"])},[t("div",null,[a(m,{for:"name",value:o.$t("name")},null,8,["value"]),a(n,{id:"name",type:"text",class:"mt-1 block w-full",modelValue:s(e).name,"onUpdate:modelValue":l[0]||(l[0]=r=>s(e).name=r),required:"",autofocus:"",autocomplete:"name"},null,8,["modelValue"]),a(i,{class:"mt-2",message:s(e).errors.name},null,8,["message"])]),t("div",h,[a(m,{for:"email",value:o.$t("email")},null,8,["value"]),a(n,{id:"email",type:"email",class:"mt-1 block w-full",modelValue:s(e).email,"onUpdate:modelValue":l[1]||(l[1]=r=>s(e).email=r),required:"",autocomplete:"username"},null,8,["modelValue"]),a(i,{class:"mt-2",message:s(e).errors.email},null,8,["message"])]),t("div",q,[a(m,{for:"password",value:o.$t("password")},null,8,["value"]),a(n,{id:"password",type:"password",class:"mt-1 block w-full",modelValue:s(e).password,"onUpdate:modelValue":l[2]||(l[2]=r=>s(e).password=r),required:"",autocomplete:"new-password"},null,8,["modelValue"]),a(i,{class:"mt-2",message:s(e).errors.password},null,8,["message"])]),t("div",B,[a(m,{for:"password_confirmation",value:o.$t("confirm_password")},null,8,["value"]),a(n,{id:"password_confirmation",type:"password",class:"mt-1 block w-full",modelValue:s(e).password_confirmation,"onUpdate:modelValue":l[3]||(l[3]=r=>s(e).password_confirmation=r),required:"",autocomplete:"new-password"},null,8,["modelValue"]),a(i,{class:"mt-2",message:s(e).errors.password_confirmation},null,8,["message"])]),t("div",U,[a(s(v),{href:o.route("login"),class:"underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"},{default:d(()=>[u(p(o.$t("already_registered")),1)]),_:1},8,["href"]),a($,{class:V(["ml-4",{"opacity-25":s(e).processing}]),disabled:s(e).processing},{default:d(()=>[u(p(o.$t("register")),1)]),_:1},8,["class","disabled"])])],40,k)]),_:1}))}};export{D as default};
